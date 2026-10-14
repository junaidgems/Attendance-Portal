<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TeamAttendanceController extends Controller
{
    /**
     * Views folder (resources/views/attendence/pages/{viewFolder}); the Director pages reuse these controllers.
     */
    protected string $viewFolder = 'manager';

    /**
     * Extra data for the page view, e.g. the Director's department filter.
     *
     * @return array<string, mixed>
     */
    protected function extraViewData(Request $request): array
    {
        return [];
    }

    protected function currentManager()
    {
        return Employee::where('user_id', Auth::id())->firstOrFail();
    }

    protected function teamMembers($manager)
    {
        return Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('reports_to_id', $manager->id)
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();
    }

    public function index(Request $request)
    {
        return view('attendence.pages.'.$this->viewFolder.'.team-attendance', $this->buildData($request) + $this->extraViewData($request));
    }

    public function export(Request $request)
    {
        $data = $this->buildData($request);

        $filename = 'team-attendance-'.$data['date'].'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Member', 'Emp ID', 'Date', 'Status', 'IN', 'OUT', 'Net Hours', 'Timeline']);
            foreach ($data['rows'] as $r) {
                fputcsv($file, [$r['name'], $r['emp_id'], $r['date'], $r['status'], $r['in'], $r['out'], $r['net'], $r['timeline']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildData(Request $request)
    {
        $manager = $this->currentManager();
        $team = $this->teamMembers($manager);

        $date = $request->input('date', now()->format('Y-m-d'));
        if ($date > now()->format('Y-m-d')) {
            $date = now()->format('Y-m-d'); // future date allowed nahi
        }
        $memberId = $request->input('member', 'all');

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();
        $workingDays = $settings ? explode(',', $settings->working_days) : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        // Every row comes from Employee::attendanceDays(), the same rules as the dashboards and the breakdown
        $rows = [];

        if ($memberId === 'all') {
            // ===== DAY VIEW: poori team, ek date =====
            foreach ($team as $emp) {
                $rows[] = $emp->teamRow($emp->attendanceDays($date, $date, $settings, $holidays)->get($date), $holidays);
            }
        } else {
            // ===== MEMBER VIEW: ek member, poora mahina =====
            $emp = $team->firstWhere('id', (int) $memberId);

            if ($emp) {
                $monthStart = Carbon::parse($date)->startOfMonth();
                $monthEnd = Carbon::parse($date)->endOfMonth();
                if ($monthEnd->gt(now())) {
                    $monthEnd = now();
                }

                foreach ($emp->attendanceDays($monthStart, $monthEnd, $settings, $holidays) as $day) {
                    $rows[] = $emp->teamRow($day, $holidays);
                }
            }
        }

        $rows = array_values(array_filter($rows, fn ($r) => $r['status'] !== ''));

        $counts = ['all' => count($rows), 'present' => 0, 'late' => 0, 'early-out' => 0, 'half-day' => 0, 'absent' => 0];
        foreach ($rows as $r) {
            foreach (['present', 'late', 'early-out', 'half-day', 'absent'] as $tag) {
                if (in_array($tag, $r['tags'])) {
                    $counts[$tag]++;
                }
            }
        }

        $d = Carbon::parse($date);
        $isHoliday = $holidays->contains(fn ($h) => $date >= $h->from_date && $date <= ($h->to_date ?? $h->from_date));
        $dayLabel = ! in_array($d->format('D'), $workingDays) ? 'Weekend' : ($isHoliday ? 'Holiday' : 'Working day');

        return [
            'rows' => $rows,
            'counts' => $counts,
            'date' => $date,
            'memberId' => $memberId,
            'team' => $team,
            'dayLabel' => $dayLabel,
            'dateDisplay' => $d->format('D d M'),
        ];
    }
}
