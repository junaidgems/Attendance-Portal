<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class PenaltyController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ?? '2026-06-26';
        $to = $request->to ?? '2026-07-25';
        $department = $request->department ?? 'all';
        $search = $request->search ?? '';

        $allEmployees = [
            [
                'employee' => 'Junaid',
                'id' => 'GP-1002',
                'dept' => 'ICT',
                'late' => 0,
                'half_days' => 0,
                'waivers' => '—',
                'cl_due' => '—',
                'cl_balance' => 10,
                'cl_deducted' => '—',
                'salary_days' => '—',
            ],
            [
                'employee' => 'Faraz Siddiqui',
                'id' => 'GP-1001',
                'dept' => 'ICT',
                'late' => 2,
                'half_days' => 1,
                'waivers' => '—',
                'cl_due' => '—',
                'cl_balance' => 8,
                'cl_deducted' => '—',
                'salary_days' => '—',
            ],
            [
                'employee' => 'John Doe',
                'id' => 'GP-1003',
                'dept' => 'Engineering',
                'late' => 4,
                'half_days' => 0,
                'waivers' => '—',
                'cl_due' => 1,
                'cl_balance' => 6,
                'cl_deducted' => 1,
                'salary_days' => 0,
            ],
            [
                'employee' => 'Jane Smith',
                'id' => 'GP-1004',
                'dept' => 'HR',
                'late' => 1,
                'half_days' => 2,
                'waivers' => '—',
                'cl_due' => 1,
                'cl_balance' => 5,
                'cl_deducted' => 1,
                'salary_days' => 0,
            ],
            [
                'employee' => 'Mike Johnson',
                'id' => 'GP-1005',
                'dept' => 'Finance',
                'late' => 6,
                'half_days' => 1,
                'waivers' => 'Pending',
                'cl_due' => 2,
                'cl_balance' => 3,
                'cl_deducted' => 2,
                'salary_days' => 1,
            ],
        ];

        // Apply filters
        $employees = $allEmployees;

        if ($department !== 'all') {
            $employees = array_filter($employees, fn($e) => $e['dept'] === $department);
        }

        if ($search) {
            $employees = array_filter($employees, function ($e) use ($search) {
                return stripos($e['employee'], $search) !== false || stripos($e['id'], $search) !== false;
            });
        }

        // ✅ SUMMARY — MANUAL SUM (100% Working)
        $clDeductedSum = 0;
        $salaryDaysSum = 0;

        foreach ($employees as $emp) {
            if (is_numeric($emp['cl_deducted'])) {
                $clDeductedSum += (int) $emp['cl_deducted'];
            }
            if (is_numeric($emp['salary_days'])) {
                $salaryDaysSum += (int) $emp['salary_days'];
            }
        }

        $summary = [
            'penalties' => count($employees),
            'cl_to_deduct' => $clDeductedSum,
            'salary_deductions' => $salaryDaysSum,
            'waivers_pending' => count(array_filter($employees, fn($e) => $e['waivers'] === 'Pending')),
        ];

        return view('attendence.pages.admin.penalty', compact('employees', 'summary', 'from', 'to', 'department', 'search'));
    }

    public function export(Request $request)
    {
        $from = $request->from ?? '2026-06-26';
        $to = $request->to ?? '2026-07-25';
        $department = $request->department ?? 'all';
        $search = $request->search ?? '';

        $allEmployees = [
            ['employee' => 'Junaid', 'id' => 'GP-1002', 'dept' => 'ICT', 'late' => 0, 'half_days' => 0, 'waivers' => '—', 'cl_due' => '—', 'cl_balance' => 10, 'cl_deducted' => '—', 'salary_days' => '—'],
            ['employee' => 'Faraz Siddiqui', 'id' => 'GP-1001', 'dept' => 'ICT', 'late' => 2, 'half_days' => 1, 'waivers' => '—', 'cl_due' => '—', 'cl_balance' => 8, 'cl_deducted' => '—', 'salary_days' => '—'],
            ['employee' => 'John Doe', 'id' => 'GP-1003', 'dept' => 'Engineering', 'late' => 4, 'half_days' => 0, 'waivers' => '—', 'cl_due' => 1, 'cl_balance' => 6, 'cl_deducted' => 1, 'salary_days' => 0],
            ['employee' => 'Jane Smith', 'id' => 'GP-1004', 'dept' => 'HR', 'late' => 1, 'half_days' => 2, 'waivers' => '—', 'cl_due' => 1, 'cl_balance' => 5, 'cl_deducted' => 1, 'salary_days' => 0],
            ['employee' => 'Mike Johnson', 'id' => 'GP-1005', 'dept' => 'Finance', 'late' => 6, 'half_days' => 1, 'waivers' => 'Pending', 'cl_due' => 2, 'cl_balance' => 3, 'cl_deducted' => 2, 'salary_days' => 1],
        ];

        $employees = $allEmployees;
        if ($department !== 'all') {
            $employees = array_filter($employees, fn($e) => $e['dept'] === $department);
        }
        if ($search) {
            $employees = array_filter($employees, function ($e) use ($search) {
                return stripos($e['employee'], $search) !== false || stripos($e['id'], $search) !== false;
            });
        }

        if (empty($employees)) {
            return back()->with('error', 'Nothing to export. No records found.');
        }

        $filename = "penalty-report-" . date('Y-m-d') . ".csv";
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Employee', 'ID', 'Department', 'Late', 'Half-Days', 'Waivers', 'CL Due', 'CL Balance', 'CL Deducted', 'Salary Days']);

        foreach ($employees as $row) {
            fputcsv($handle, [
                $row['employee'],
                $row['id'],
                $row['dept'],
                $row['late'],
                $row['half_days'],
                $row['waivers'],
                $row['cl_due'],
                $row['cl_balance'],
                $row['cl_deducted'],
                $row['salary_days'],
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}