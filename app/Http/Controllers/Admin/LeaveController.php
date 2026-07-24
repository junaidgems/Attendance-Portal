<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class LeaveController extends Controller
{
    public function index()
    {
        // Dummy Leave Types
        $leaveTypes = [
            [
                'id' => 1,
                'name' => 'Annual Leave',
                'days' => 14,
                'in_use' => 2,
                'color' => '#3b82f6'
            ],
            [
                'id' => 2,
                'name' => 'Sick Leave',
                'days' => 8,
                'in_use' => 1,
                'color' => '#22c55e'
            ],
            [
                'id' => 3,
                'name' => 'Casual Leave',
                'days' => 10,
                'in_use' => 0,
                'color' => '#f59e0b'
            ],
        ];

        // ===== LEAVE REQUESTS WITH DIFFERENT STATUSES =====
        $leaveRequests = [
            [
                'id' => 1,
                'employee' => 'John Doe',
                'avatar' => 'JD',
                'type' => 'Annual Leave',
                'from' => '2026-07-20',
                'to' => '2026-07-22',
                'days' => 3,
                'status' => 'pending',
                'reason' => 'Family event'
            ],
            [
                'id' => 2,
                'employee' => 'Jane Smith',
                'avatar' => 'JS',
                'type' => 'Sick Leave',
                'from' => '2026-07-21',
                'to' => '2026-07-21',
                'days' => 1,
                'status' => 'pending',
                'reason' => 'Doctor appointment'
            ],
            [
                'id' => 3,
                'employee' => 'Mike Johnson',
                'avatar' => 'MJ',
                'type' => 'Annual Leave',
                'from' => '2026-07-18',
                'to' => '2026-07-19',
                'days' => 2,
                'status' => 'approved',
                'reason' => 'Weekend trip'
            ],
            [
                'id' => 4,
                'employee' => 'Sarah Lee',
                'avatar' => 'SL',
                'type' => 'Casual Leave',
                'from' => '2026-07-15',
                'to' => '2026-07-15',
                'days' => 1,
                'status' => 'approved',
                'reason' => 'Personal work'
            ],
            [
                'id' => 5,
                'employee' => 'David Khan',
                'avatar' => 'DK',
                'type' => 'Sick Leave',
                'from' => '2026-07-14',
                'to' => '2026-07-14',
                'days' => 1,
                'status' => 'rejected',
                'reason' => 'Insufficient balance'
            ],
            [
                'id' => 6,
                'employee' => 'Ali Hassan',
                'avatar' => 'AH',
                'type' => 'Annual Leave',
                'from' => '2026-07-10',
                'to' => '2026-07-12',
                'days' => 3,
                'status' => 'rejected',
                'reason' => 'Team conflict'
            ],
        ];

        return view('attendence.pages.admin.leaves', compact('leaveTypes', 'leaveRequests'));
    }

    public function report()
    {
        // ===== DUMMY BALANCES DATA =====
        $balances = [
            [
                'employee' => 'Junaid',
                'id' => 'GP-1002',
                'dept' => 'ICT',
                'annual' => ['used' => 0, 'total' => 14],
                'sick' => ['used' => 0, 'total' => 8],
                'casual' => ['used' => 0, 'total' => 10],
                'total_taken' => 0,
            ],
            [
                'employee' => 'Faraz Siddiqui',
                'id' => 'GP-1001',
                'dept' => 'ICT',
                'annual' => ['used' => 3, 'total' => 14],
                'sick' => ['used' => 1, 'total' => 8],
                'casual' => ['used' => 2, 'total' => 10],
                'total_taken' => 6,
            ],
            [
                'employee' => 'John Doe',
                'id' => 'GP-1003',
                'dept' => 'Engineering',
                'annual' => ['used' => 5, 'total' => 14],
                'sick' => ['used' => 0, 'total' => 8],
                'casual' => ['used' => 1, 'total' => 10],
                'total_taken' => 6,
            ],
        ];

        // ===== DUMMY REGISTER DATA =====
        $register = [
            [
                'employee' => 'John Doe',
                'id' => 'GP-1003',
                'dept' => 'Engineering',
                'type' => 'Annual Leave',
                'from' => '2026-07-20',
                'to' => '2026-07-22',
                'days' => 3,
                'status' => 'approved',
                'applied' => '2026-07-15'
            ],
            [
                'employee' => 'Jane Smith',
                'id' => 'GP-1004',
                'dept' => 'HR',
                'type' => 'Sick Leave',
                'from' => '2026-07-18',
                'to' => '2026-07-18',
                'days' => 1,
                'status' => 'pending',
                'applied' => '2026-07-17'
            ],
            [
                'employee' => 'Mike Johnson',
                'id' => 'GP-1005',
                'dept' => 'Finance',
                'type' => 'Casual Leave',
                'from' => '2026-07-14',
                'to' => '2026-07-14',
                'days' => 1,
                'status' => 'rejected',
                'applied' => '2026-07-12'
            ],
            [
                'employee' => 'Sarah Lee',
                'id' => 'GP-1006',
                'dept' => 'Marketing',
                'type' => 'Annual Leave',
                'from' => '2026-07-25',
                'to' => '2026-07-26',
                'days' => 2,
                'status' => 'pending',
                'applied' => '2026-07-20'
            ],
        ];

        $summary = [
            'approved_days' => 3,      // John Doe ki 3 days approved
            'pending_requests' => 2,   // Jane Smith + Sarah Lee pending
            'rejected_requests' => 1,  // Mike Johnson rejected
            'on_leave_today' => 0,
        ];

        return view('attendence.pages.admin.leave-report', compact('balances', 'register', 'summary'));
    }

    // ✅ EXPORT BALANCES
    public function exportBalances(Request $request)
    {
        $balances = [
            ['Employee' => 'Junaid', 'ID' => 'GP-1002', 'Dept' => 'ICT', 'Annual' => '0/14', 'Sick' => '0/8', 'Casual' => '0/10', 'Total Taken' => 0],
            ['Employee' => 'Faraz Siddiqui', 'ID' => 'GP-1001', 'Dept' => 'ICT', 'Annual' => '3/14', 'Sick' => '1/8', 'Casual' => '2/10', 'Total Taken' => 6],
            ['Employee' => 'John Doe', 'ID' => 'GP-1003', 'Dept' => 'Engineering', 'Annual' => '5/14', 'Sick' => '0/8', 'Casual' => '1/10', 'Total Taken' => 6],
        ];

        if (empty($balances)) {
            return back()->with('error', 'Nothing to export. No leave balances found.');
        }

        $filename = "leave-balances-" . date('Y-m-d') . ".csv";
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, array_keys($balances[0]));

        foreach ($balances as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    // ✅ EXPORT REGISTER

    public function exportRegister(Request $request)
    {
        $register = [
            ['employee' => 'John Doe', 'id' => 'GP-1003', 'dept' => 'Engineering', 'type' => 'Annual Leave', 'from' => '2026-07-20', 'to' => '2026-07-22', 'days' => 3, 'status' => 'approved', 'applied' => '2026-07-15'],
            ['employee' => 'Jane Smith', 'id' => 'GP-1004', 'dept' => 'HR', 'type' => 'Sick Leave', 'from' => '2026-07-18', 'to' => '2026-07-18', 'days' => 1, 'status' => 'pending', 'applied' => '2026-07-17'],
            ['employee' => 'Mike Johnson', 'id' => 'GP-1005', 'dept' => 'Finance', 'type' => 'Casual Leave', 'from' => '2026-07-14', 'to' => '2026-07-14', 'days' => 1, 'status' => 'rejected', 'applied' => '2026-07-12'],
            ['employee' => 'Sarah Lee', 'id' => 'GP-1006', 'dept' => 'Marketing', 'type' => 'Annual Leave', 'from' => '2026-07-25', 'to' => '2026-07-26', 'days' => 2, 'status' => 'pending', 'applied' => '2026-07-20'],
        ];

        // ✅ Agar data empty hai toh alert dikhao
        if (empty($register)) {
            return back()->with('error', 'Nothing to export. No leave records found.');
        }

        $filename = "leave-register-" . date('Y-m-d') . ".csv";
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Employee', 'ID', 'Department', 'Leave Type', 'From', 'To', 'Days', 'Status', 'Applied']);

        foreach ($register as $row) {
            fputcsv($handle, [
                $row['employee'],
                $row['id'],
                $row['dept'],
                $row['type'],
                $row['from'],
                $row['to'],
                $row['days'],
                $row['status'],
                $row['applied']
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

