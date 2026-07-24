<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index()
    {
        // Dummy attendance data
        $attendances = [
            [
                'id' => 'GP-1001',
                'name' => 'Faraz Siddiqui',
                'department' => 'ICT',
                'status' => 'Present',
                'in' => '09:00',
                'out' => '18:00',
                'net' => '9h 00m',
                'timeline' => 'On Time'
            ],
            [
                'id' => 'GP-1002',
                'name' => 'John Doe',
                'department' => 'Engineering',
                'status' => 'Late',
                'in' => '09:35',
                'out' => '18:30',
                'net' => '8h 55m',
                'timeline' => 'Late by 35m'
            ],
            [
                'id' => 'GP-1003',
                'name' => 'Jane Smith',
                'department' => 'HR',
                'status' => 'Absent',
                'in' => '—',
                'out' => '—',
                'net' => '—',
                'timeline' => 'No Punch'
            ],
            [
                'id' => 'GP-1004',
                'name' => 'Mike Johnson',
                'department' => 'Finance',
                'status' => 'Leave',
                'in' => '—',
                'out' => '—',
                'net' => '—',
                'timeline' => 'Approved Leave'
            ],
            [
                'id' => 'GP-1005',
                'name' => 'Sarah Lee',
                'department' => 'Marketing',
                'status' => 'Present',
                'in' => '08:55',
                'out' => '17:30',
                'net' => '8h 35m',
                'timeline' => 'On Time'
            ],
            [
                'id' => 'GP-1006',
                'name' => 'David Khan',
                'department' => 'Operations',
                'status' => 'Late',
                'in' => '09:50',
                'out' => '18:15',
                'net' => '8h 25m',
                'timeline' => 'Late by 50m'
            ],
        ];

        $isNonWorkingDay = true; // For demo

        return view('attendence.pages.admin.attendance', compact('attendances', 'isNonWorkingDay'));
    }

    public function mark(Request $request)
    {
        // Mark attendance logic
        return redirect()->back()->with('success', 'Attendance marked successfully!');
    }

    public function export(Request $request)
    {
        // Export logic
        return redirect()->back()->with('success', 'Export initiated!');
    }
}