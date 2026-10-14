<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;

/**
 * The Line Manager dashboard and My Attendance: the Manager pages with the line-manager views.
 */
class DashboardController extends ManagerDashboardController
{
    protected string $viewFolder = 'line-manager';
}
