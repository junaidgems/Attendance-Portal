<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CorrectionRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $settings = DB::table('attendance_settings')->first();
        $today = now()->format('Y-m-d');

        $holidays = DB::table('holidays')->get();

        // ===== HEADCOUNT =====
        $activeEmployees = Employee::with(['department', 'shift', 'gracePolicy', 'role'])
            ->where('employment_status', 'active')
            ->get();

        $headcount = $activeEmployees->count();
        $adminCount = $activeEmployees->where('role.name', 'administrator')->count();
        $staffCount = $headcount - $adminCount;

        // ===== Each employee's last 30 days, with the same rules as the attendance pages
        // (approved leave, waivers, corrections, WFH, today's check-in still in progress) =====
        $trackedEmployees = $activeEmployees->filter(fn ($emp) => $emp->zkteco_uid);
        $employeeDays = $trackedEmployees->mapWithKeys(fn ($emp) => [$emp->id => $emp->attendanceDays(now()->subDays(30), now(), $settings, $holidays)]);

        // ===== TODAY =====
        $inOfficeNow = 0;
        $onTimeNow = 0;
        $lateToday = 0;
        $absentToday = 0;

        foreach ($employeeDays as $days) {
            $day = $days->get($today);
            $r = $day['record'];

            if ($r?->check_in && ! $r->check_out) {
                $inOfficeNow++;
                if ($day['status'] !== 'Late') {
                    $onTimeNow++;
                }
            }
            if ($day['status'] === 'Late') {
                $lateToday++;
            }
            if ($day['status'] === 'Absent') {
                $absentToday++;
            }
        }

        // ===== CORRECTIONS SUMMARY =====
        $pendingCorrections = CorrectionRequest::where('status', 'pending')->count();
        $approvedCorrections = CorrectionRequest::where('status', 'approved')->count();
        $rejectedCorrections = CorrectionRequest::where('status', 'rejected')->count();

        // ===== ATTENDANCE BY DEPARTMENT (30-day rate; approved leave days are not counted against attendance) =====
        $departments = Department::orderBy('name')->get();
        $deptStats = [];

        foreach ($departments as $dept) {
            $deptDays = $trackedEmployees->where('department_id', $dept->id)
                ->flatMap(fn ($emp) => $employeeDays[$emp->id]->values());
            if ($deptDays->isEmpty()) {
                continue;
            }

            $expected = $deptDays->whereNotIn('status', ['', 'Weekend', 'Holiday', 'On Leave'])->count();
            $present = $deptDays->filter(fn ($d) => $d['record']?->check_in && ! in_array($d['status'], ['Weekend', 'On Leave'], true))->count();

            $deptStats[] = ['name' => $dept->name, 'rate' => $expected > 0 ? round(($present / $expected) * 100) : 0];
        }

        // ===== TODAY'S EXCEPTIONS =====
        $exceptions = [];

        foreach ($trackedEmployees as $emp) {
            $day = $employeeDays[$emp->id]->get($today);
            $r = $day['record'];
            if ($day['status'] === 'Late') {
                $exceptions[] = [
                    'name' => $emp->first_name.' '.$emp->last_name,
                    'text' => 'Late in ('.Carbon::parse($r->check_in)->format('H:i').')'.($day['wfh'] ? ' · '.$day['wfh'] : ''),
                    'dot' => 'yellow',
                    'time' => Carbon::parse($r->updated_at)->diffForHumans(),
                    'sort' => $r->updated_at,
                ];
            }
        }

        // Correction decisions of the last 24 hours (an approved correction has already changed the attendance)
        $recentCorrections = CorrectionRequest::with('employee')
            ->whereIn('status', ['approved', 'rejected'])
            ->where('reviewed_at', '>=', now()->subDay())
            ->get();

        foreach ($recentCorrections as $c) {
            if (! $c->employee) {
                continue;
            }
            $exceptions[] = [
                'name' => $c->employee->first_name.' '.$c->employee->last_name,
                'text' => 'Correction '.$c->status.' ('.$c->typeLabel().' · '.Carbon::parse($c->date)->format('d M').')',
                'dot' => $c->status === 'approved' ? 'green' : 'red',
                'time' => Carbon::parse($c->reviewed_at)->diffForHumans(),
                'sort' => $c->reviewed_at,
            ];
        }

        $todayLeaves = LeaveRequest::with('employee')
            ->approvedAbsence()
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->get();

        foreach ($todayLeaves as $l) {
            if (! $l->employee) {
                continue;
            }
            $exceptions[] = [
                'name' => $l->employee->first_name.' '.$l->employee->last_name,
                'text' => 'Leave (Approved)',
                'dot' => 'blue',
                'time' => Carbon::parse($l->reviewed_at ?? $l->updated_at)->diffForHumans(),
                'sort' => $l->reviewed_at ?? $l->updated_at,
            ];
        }

        usort($exceptions, fn ($a, $b) => strtotime($b['sort']) <=> strtotime($a['sort']));
        $exceptions = array_slice($exceptions, 0, 6);

        // ===== MONTHLY STATS (org-wide, current month) =====
        $monthStart = now()->startOfMonth()->toDateString();
        $monthDays = $employeeDays->flatMap(fn ($days) => $days->filter(fn ($d) => $d['date'] >= $monthStart)->values());
        $monthly = [
            'missing-punch' => $monthDays->where('status', 'Missing Punch')->count(),
            'half-day' => $monthDays->where('status', 'Half Day')->count(),
            'early-out' => $monthDays->where('status', 'Early Out')->count(),
            'absent' => $monthDays->where('status', 'Absent')->filter(fn ($d) => $d['date'] < $today)->count(),
            'late' => $monthDays->where('status', 'Late')->count(),
            'present' => $monthDays->where('status', 'Present')->count(),
        ];

        // ===== NOTIFICATIONS (from audit log + pending leaves) =====
        $notifications = [];

        $lastCycleLog = AuditLog::where('category', 'cycle')->latest('created_at')->first();
        if ($lastCycleLog) {
            $notifications[] = ['text' => '<strong>Attendance Policy</strong> updated', 'dot' => 'blue', 'time' => Carbon::parse($lastCycleLog->created_at)->diffForHumans()];
        }

        $pendingLeaves = LeaveRequest::awaitingAdmin()->count();
        if ($pendingLeaves > 0) {
            $notifications[] = ['text' => "<strong>{$pendingLeaves} leave requests</strong> pending approval", 'dot' => 'green', 'time' => 'now'];
        }

        $lastSyncLog = AuditLog::where('category', 'sync')->latest('created_at')->first();
        if ($lastSyncLog) {
            $notifications[] = ['text' => '<strong>Biometric sync</strong> completed', 'dot' => 'yellow', 'time' => Carbon::parse($lastSyncLog->created_at)->diffForHumans()];
        }

        return view('attendence.pages.admin.dashboard', [
            'headcount' => $headcount,
            'staffCount' => $staffCount,
            'adminCount' => $adminCount,
            'inOfficeNow' => $inOfficeNow,
            'onTimeNow' => $onTimeNow,
            'lateToday' => $lateToday,
            'absentToday' => $absentToday,
            'pendingCorrections' => $pendingCorrections,
            'approvedCorrections' => $approvedCorrections,
            'rejectedCorrections' => $rejectedCorrections,
            'deptStats' => $deptStats,
            'exceptions' => $exceptions,
            'monthly' => $monthly,
            'notifications' => $notifications,
        ]);
    }
}
