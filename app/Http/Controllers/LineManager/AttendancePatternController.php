<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Manager\AttendancePatternController as ManagerAttendancePatternController;

/**
 * Attendance patterns of the Line Manager's team: the Manager page with the line-manager view.
 */
class AttendancePatternController extends ManagerAttendancePatternController
{
    protected string $viewFolder = 'line-manager';
}
