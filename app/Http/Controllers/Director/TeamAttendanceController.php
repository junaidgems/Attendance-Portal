<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Director\Concerns\CompanyWide;
use App\Http\Controllers\Manager\TeamAttendanceController as ManagerTeamAttendanceController;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Day-by-day attendance of the whole company (the Manager page with every active employee),
 * optionally of one department.
 */
class TeamAttendanceController extends ManagerTeamAttendanceController
{
    use CompanyWide {
        teamMembers as companyMembers;
    }

    protected string $viewFolder = 'director';

    protected function teamMembers($manager)
    {
        $departmentId = request()->integer('department');

        return $departmentId
            ? $this->companyMembers($manager)->where('department_id', $departmentId)->values()
            : $this->companyMembers($manager);
    }

    /**
     * @return array{departments: Collection, departmentId: int}
     */
    protected function extraViewData(Request $request): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'departmentId' => $request->integer('department'),
        ];
    }
}
