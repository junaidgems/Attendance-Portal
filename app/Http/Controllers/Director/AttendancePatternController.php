<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Director\Concerns\CompanyWide;
use App\Http\Controllers\Manager\AttendancePatternController as ManagerAttendancePatternController;

/**
 * Attendance patterns of the whole company (the Manager page with every active employee).
 */
class AttendancePatternController extends ManagerAttendancePatternController
{
    use CompanyWide;

    protected string $viewFolder = 'director';
}
