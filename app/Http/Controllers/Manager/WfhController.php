<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Employee\WfhController as EmployeeWfhController;

/**
 * The Manager's own Work From Home. Same rules as employees; a Manager's request goes straight to Admin.
 */
class WfhController extends EmployeeWfhController
{
    protected string $viewFolder = 'manager';
}
