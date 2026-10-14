<?php

namespace App\Http\Controllers\LineManager;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance patterns of the Line Manager's team over a period.
 * Reuses the day-status rules of TeamAttendanceController so both pages show the same numbers.
 */
class AttendancePatternController extends TeamAttendanceController
{
    /**
     * Member is flagged when late arrivals reach this count.
     */
    private const WATCH_LATE_COUNT = 3;

    /**
     * Member is flagged when attendance drops below this percentage.
     */
    private const WATCH_ATTENDANCE_PERCENT = 90;

    public function index(Request $request)
    {
        return view('attendence.pages.line-manager.attendance-patterns', $this->buildPatterns($request));
    }

    public function export(Request $request)
    {
        $data = $this->buildPatterns($request);

        $filename = 'attendance-patterns-'.$data['from'].'-to-'.$data['to'].'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Member', 'Emp ID', 'Department', 'Attendance %', 'Late Arrivals', 'Early Out', 'Half Day', 'Absent', 'On Leave', 'Punctuality', 'Avg Hours', 'Needs Attention']);
            foreach ($data['members'] as $m) {
                fputcsv($file, [
                    $m['name'], $m['emp_id'], $m['department'], $m['attendance'], $m['late'], $m['early'],
                    $m['half'], $m['absent'], $m['leave'], $m['punctuality'], $m['avg_hours'] ?? '-',
                    $m['watch'] ? 'Yes' : 'No',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array{members: array<int, array<string, mixed>>, summary: array{avg_attendance: ?float, total_late: int, watch_count: int}, departments: Collection, period: string, department: string, from: string, to: string, periodLabel: string}
     */
    private function buildPatterns(Request $request): array
    {
        $manager = $this->currentManager();
        $team = $this->teamMembers($manager);

        $period = in_array($request->input('period'), ['30', '60', '90', 'cycle'], true) ? $request->input('period') : '30';
        $department = $request->input('department', 'all');

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();

        [$from, $to] = $this->periodRange($period, $settings);

        $departments = $team->pluck('department')->filter()->unique('id')->sortBy('name')->values();

        if ($department !== 'all') {
            $team = $team->where('department_id', (int) $department)->values();
        }

        $records = DB::table('device_attendance')
            ->whereIn('uid', $team->pluck('zkteco_uid')->filter())
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get()
            ->groupBy(fn ($r) => (string) $r->uid);

        $leaves = LeaveRequest::whereIn('employee_id', $team->pluck('id'))
            ->approvedAbsence()
            ->whereDate('from_date', '<=', $to->format('Y-m-d'))
            ->whereDate('to_date', '>=', $from->format('Y-m-d'))
            ->get()
            ->groupBy('employee_id');

        $members = [];

        foreach ($team as $emp) {
            $empRecords = $emp->zkteco_uid
                ? $records->get((string) $emp->zkteco_uid, collect())->keyBy('date')
                : collect();

            $members[] = $this->memberPattern($emp, $from, $to, $empRecords, $settings, $holidays, $leaves->get($emp->id, collect()));
        }

        usort($members, fn ($a, $b) => [$b['watch'], $a['name']] <=> [$a['watch'], $b['name']]);

        $withAttendance = array_filter($members, fn ($m) => $m['attendance'] !== null);

        return [
            'members' => $members,
            'summary' => [
                'avg_attendance' => $withAttendance
                    ? round(array_sum(array_column($withAttendance, 'attendance')) / count($withAttendance))
                    : null,
                'total_late' => array_sum(array_column($members, 'late')),
                'watch_count' => count(array_filter($members, fn ($m) => $m['watch'])),
            ],
            'departments' => $departments,
            'period' => $period,
            'department' => $department,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'periodLabel' => $from->format('d M').' – '.$to->format('d M Y'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period, ?object $settings): array
    {
        $today = now()->startOfDay();

        if ($period !== 'cycle') {
            return [$today->copy()->subDays((int) $period - 1), $today];
        }

        $startDay = (int) ($settings->cycle_start_day ?? 1);
        $cycleStart = Carbon::create($today->year, $today->month, min($startDay, $today->daysInMonth));

        if ($cycleStart->gt($today)) {
            $previous = $today->copy()->subMonthNoOverflow();
            $cycleStart = Carbon::create($previous->year, $previous->month, min($startDay, $previous->daysInMonth));
        }

        return [$cycleStart, $today];
    }

    /**
     * @return array{name: string, emp_id: string, department: string, attendance: ?int, late: int, early: int, half: int, absent: int, leave: int, punctuality: string, avg_hours: ?float, watch: bool}
     */
    private function memberPattern($emp, Carbon $from, Carbon $to, $records, $settings, $holidays, $leaves): array
    {
        $counts = ['attended' => 0.0, 'expected' => 0, 'late' => 0, 'early' => 0, 'half' => 0, 'absent' => 0, 'leave' => 0];
        $workedMinutes = [];

        // Days before the joining date are not counted as absent
        $cursor = $emp->joining_date ? $from->copy()->max(Carbon::parse($emp->joining_date)) : $from->copy();
        while ($cursor->lte($to)) {
            $dateStr = $cursor->format('Y-m-d');
            $row = $this->buildRow($emp, $dateStr, $records->get($dateStr), $settings, $holidays, $leaves);
            $cursor->addDay();

            switch ($row['status']) {
                case 'On Leave':
                    $counts['leave']++;
                    break;
                case 'Absent':
                    $counts['expected']++;
                    $counts['absent']++;
                    break;
                case 'Present':
                case 'Late':
                case 'Early Out':
                case 'Half Day':
                    $counts['expected']++;
                    $counts['attended'] += $row['status'] === 'Half Day' ? 0.5 : 1;
                    $counts['late'] += $row['status'] === 'Late' ? 1 : 0;
                    $counts['early'] += $row['status'] === 'Early Out' ? 1 : 0;
                    $counts['half'] += $row['status'] === 'Half Day' ? 1 : 0;
                    break;
            }

            if ($row['net_minutes']) {
                $workedMinutes[] = $row['net_minutes'];
            }
        }

        $attendance = $counts['expected'] > 0 ? (int) round($counts['attended'] / $counts['expected'] * 100) : null;
        $daysPresent = $counts['expected'] - $counts['absent'];
        $lateRatio = $daysPresent > 0 ? $counts['late'] / $daysPresent : 0;

        return [
            'name' => $emp->full_name,
            'emp_id' => $emp->employee_id,
            'department' => $emp->department->name ?? '-',
            'attendance' => $attendance,
            'late' => $counts['late'],
            'early' => $counts['early'],
            'half' => $counts['half'],
            'absent' => $counts['absent'],
            'leave' => $counts['leave'],
            'punctuality' => match (true) {
                $daysPresent === 0 => 'N/A',
                $lateRatio <= 0.1 => 'Good',
                $lateRatio <= 0.25 => 'Average',
                default => 'Poor',
            },
            'avg_hours' => $workedMinutes ? round(array_sum($workedMinutes) / count($workedMinutes) / 60, 1) : null,
            'watch' => $counts['late'] >= self::WATCH_LATE_COUNT
                || $counts['absent'] > 0
                || ($attendance !== null && $attendance < self::WATCH_ATTENDANCE_PERCENT),
        ];
    }
}
