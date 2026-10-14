<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceBreakdownController extends Controller
{
    private const PER_PAGE = 25;

    /**
     * Tab key => ?type= value used by the dashboard links (keeps the tab open while paging).
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

        return view('attendence.pages.admin.attendance-breakdown', $data);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->buildBreakdown($request);
        $tab = array_key_exists((string) $request->input('tab'), self::TAB_TYPES) ? $request->input('tab') : 'all';

        $filename = 'attendance-breakdown-'.$data['from'].'-to-'.$data['to'].'.csv';

        return response()->streamDownload(function () use ($data, $tab) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee', 'Emp ID', 'Department', 'Date', 'Status', 'Shift', 'Scheduled', 'IN', 'OUT', 'Worked', 'Late', 'Early', 'Remarks']);
            foreach ($data['grouped'][$tab] as $r) {
                fputcsv($file, [$r['employee'], $r['emp_id'], $r['department'], $r['date'], $r['status'], $r['shift'], $r['scheduled'], $r['in'], $r['out'], $r['worked'], $r['late'], $r['early'], $r['remarks']]);
            }
            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{grouped: array<string, Collection>, from: string, to: string, department: string, search: ?string, departments: Collection}
     */
    private function buildBreakdown(Request $request): array
    {
        // Default: this month, the same period as the dashboard cards that link here
        $today = now()->startOfDay();
        $from = Carbon::parse($request->input('from', $today->copy()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', $today->toDateString()))->startOfDay()->min($today);
        if ($from->gt($to)) {
            $from = $to->copy();
        }

        $department = $request->input('department', 'all');
        $search = $request->input('search');

        $settings = DB::table('attendance_settings')->first();
        $holidays = DB::table('holidays')->get();

        $query = Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('employment_status', 'active');

        if ($department !== 'all') {
            $query->whereHas('department', fn ($q) => $q->where('name', $department));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                    ->orWhere('last_name', 'like', "%$search%")
                    ->orWhere('employee_id', 'like', "%$search%");
            });
        }

        // Same rules as All Attendance and the dashboards: leave, waivers, corrections, WFH, today in progress
        $rows = $query->orderBy('first_name')->get()
            ->flatMap(fn ($emp) => $emp->attendanceDays($from, $to, $settings, $holidays)
                ->reject(fn ($day) => in_array($day['status'], ['', 'Weekend'], true))
                ->map(fn ($day) => $emp->breakdownRow($day, $settings))
                ->values());

        return [
            'grouped' => Employee::breakdownTabs($rows),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department' => $department,
            'search' => $search,
            'departments' => Department::orderBy('name')->get(),
        ];
    }
}
