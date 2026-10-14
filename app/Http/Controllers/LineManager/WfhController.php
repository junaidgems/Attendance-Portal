<?php

namespace App\Http\Controllers\LineManager;

use App\Http\Controllers\Employee\WfhController as EmployeeWfhController;

/**
 * Work From Home for Line Managers and Managers. Same rules and page as employees; a Line Manager's
 * request goes to their Manager, a Manager's request goes straight to Admin.
 */
class WfhController extends EmployeeWfhController {}
