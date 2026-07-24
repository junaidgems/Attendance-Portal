<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index()
    {
        // ===== DUMMY DATA — JAB DATA HO =====
        $data = [
            'corrections' => [
                [
                    'id' => 1,
                    'employee' => 'John Doe',
                    'avatar' => 'JD',
                    'date' => '2026-07-17',
                    'type' => 'Missing Punch',
                    'requested_time' => '09:15',
                    'reason' => 'Forgot to punch in',
                    'status' => 'pending'
                ],
                [
                    'id' => 2,
                    'employee' => 'Jane Smith',
                    'avatar' => 'JS',
                    'date' => '2026-07-16',
                    'type' => 'Late Punch',
                    'requested_time' => '10:30',
                    'reason' => 'Traffic jam',
                    'status' => 'pending'
                ],
                [
                    'id' => 3,
                    'employee' => 'Mike Khan',
                    'avatar' => 'MK',
                    'date' => '2026-07-15',
                    'type' => 'Missing Punch',
                    'requested_time' => '14:00',
                    'reason' => 'On-site meeting',
                    'status' => 'pending'
                ],
            ],
            'leaves' => [
                [
                    'id' => 4,
                    'employee' => 'Sarah Lee',
                    'avatar' => 'SL',
                    'date' => '2026-07-20',
                    'type' => 'Annual Leave',
                    'duration' => '3 days',
                    'reason' => 'Family event',
                    'status' => 'pending'
                ],
                [
                    'id' => 5,
                    'employee' => 'David Khan',
                    'avatar' => 'DK',
                    'date' => '2026-07-22',
                    'type' => 'Sick Leave',
                    'duration' => '1 day',
                    'reason' => 'Doctor appointment',
                    'status' => 'pending'
                ],
            ],
            'waivers' => [
                [
                    'id' => 6,
                    'employee' => 'John Doe',
                    'avatar' => 'JD',
                    'date' => '2026-07-17',
                    'type' => 'Late Deduction',
                    'reason' => 'Traffic jam',
                    'status' => 'pending'
                ],
            ],
        ];

        return view('attendence.pages.admin.approvals', compact('data'));
    }
}