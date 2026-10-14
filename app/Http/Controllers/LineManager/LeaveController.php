<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Employee\empLeaveController;

/**
 * Own leave requests of Line Managers and Managers.
 * Reuses the employee logic (balance, half day, overlap); only the view differs.
 * A Line Manager's request goes to their Manager first; a Manager's request goes straight to Admin.
 */
class LeaveController extends empLeaveController
{
    protected string $viewFolder = 'line-manager';
}
