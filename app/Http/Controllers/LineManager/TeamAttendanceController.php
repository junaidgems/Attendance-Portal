<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Manager\TeamAttendanceController as ManagerTeamAttendanceController;

/**
 * Day-by-day attendance of the Line Manager's team: the Manager page with the line-manager view.
 */
class TeamAttendanceController extends ManagerTeamAttendanceController
{
    protected string $viewFolder = 'line-manager';
}
