<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Employee\empLeaveController;

/**
 * The Manager's own leave requests (same rules as employees); a Manager's request goes straight to Admin.
 */
class LeaveController extends empLeaveController
{
    protected string $viewFolder = 'manager';
}
