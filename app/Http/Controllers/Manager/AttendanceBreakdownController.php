<?php

namespace App\Http\Controllers\Manager;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Day-by-day attendance breakdown of the Line Manager's team.
 * Uses the same day-status rules as Team Attendance and Attendance Patterns.
 */
class AttendanceBreakdownController extends TeamAttendanceController
{
    /**
     * Tab key => row filter. Keys match the ?type= links on the dashboard via the view's tab map.
     */
    private const TABS = ['all', 'missing', 'halfday', 'earlyout', 'absent', 'late', 'present', 'onleave'];

    private const PER_PAGE = 25;

    /**
     * Tab key => ?type= value used by dashboard links (keeps the tab open while paging).
     */
    private const TAB_TYPES = [
        'all' => 'all',
        'missing' => 'missing-punch',
        'halfday' => 'half-day',
        'earlyout' => 'early-out',
        'absent' => 'absent',
        'late' => 'late',
        'present' => 'present',
        'onleave' => 'on-leave',
    ];

    public function index(Request $request)
    {
        $data = $this->buildBreakdown($request);

        // Each tab pages independently: ?all_page=2, ?late_page=3 ...
        $data['pages'] = collect($data['grouped'])->map(function (Collection $rows, string $key) use ($request) {
            $pageName = $key.'_page';
            $page = max(1, (int) $request->input($pageName, 1));

            return (new LengthAwarePaginator(
                $rows->forPage($page, self::PER_PAGE)->values(),
                $rows->count(),
                self::PER_PAGE,
                $page,
                ['path' => $request->url(), 'pageName' => $pageName]
            ))->withQueryString()->appends('type', self::TAB_TYPES[$key]);
        })->all();

        return view('attendence.pages.'.$this->viewFolder.'.attendance-breakdown', $data + $this->extraViewData($request));
    }

    public function export(Request $request)
    {
        $data = $this->buildBreakdown($request);
        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'all';

        $filename = 'team-attendance-breakdown-'.$data['from'].'-to-'.$data['to'].'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data, $tab) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee', 'Emp ID', 'Department', 'Date', 'Status', 'Shift', 'Scheduled', 'IN', 'OUT', 'Worked', 'Late', 'Early']);
            foreach ($data['grouped'][$tab] as $r) {
                fputcsv($file, [$r['employee'], $r['emp_id'], $r['department'], $r['date'], $r['status'], $r['shift'], $r['scheduled'], $r['in'], $r['out'], $r['worked'], $r['late'], $r['early']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array{grouped: array<string, Collection>, from: string, to: string, department: string, search: ?string, departments: Collection}
     */
    private function buildBreakdown(Request $request): array
    {
        $manager = $this->currentManager();
        $team = $this->teamMembers($manager);

        $today = now()->startOfDay();
        $from = Carbon::parse($request->input('from', $today->copy()->startOfMonth()->format('Y-m-d')))->startOfDay();
        $to = Carbon::parse($request->input('to', $today->format('Y-m-d')))->startOfDay()->min($today);
        if ($from->gt($to)) {
            $from = $to->copy();
        }

        $department = $request->input('department', 'all');
        $search = trim((string) $request->input('search'));

        $departments = $team->pluck('department')->filter()->unique('id')->sortBy('name')->values();
        $members = $team->values();
        $member = $request->input('member', 'all');

        if ($member !== 'all') {
            $team = $team->where('id', (int) $member);
        }

        if ($department !== 'all') {
            $team = $team->where('department_id', (int) $department);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $team = $team->filter(fn ($e) => str_contains(mb_strtolower($e->full_name), $needle)
                || str_contains(mb_strtolower((string) $e->employee_id), $needle));
        }

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();

        // Same rules as Team Attendance and the dashboards: leave, waivers, corrections, WFH, today in progress
        $rows = $team->flatMap(fn ($emp) => $emp->attendanceDays($from, $to, $settings, $holidays)
            ->reject(fn ($day) => in_array($day['status'], ['', 'Weekend'], true))
            ->map(fn ($day) => $emp->breakdownRow($day, $settings))
            ->values());

        return [
            'grouped' => Employee::breakdownTabs($rows),
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'department' => $department,
            'search' => $search,
            'departments' => $departments,
            'member' => $member,
            'members' => $members,
        ];
    }
}
