<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Director\Concerns\CompanyWide;
use App\Http\Controllers\Manager\AttendanceBreakdownController as ManagerAttendanceBreakdownController;

/**
 * Attendance breakdown (late, half day, missing punch...) of the whole company.
 */
class AttendanceBreakdownController extends ManagerAttendanceBreakdownController
{
    use CompanyWide;

    protected string $viewFolder = 'director';
}
