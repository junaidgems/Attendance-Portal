<?php

namespace App\Http\Controllers\Director\Concerns;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

/**
 * The Director's team pages reuse the Manager controllers, with the whole company instead of the
 * people reporting to them.
 */
trait CompanyWide
{
    protected function currentManager()
    {
        return Auth::user()->employee;
    }

    /**
     * Every active employee except Directors.
     */
    protected function teamMembers($manager)
    {
        return Employee::with(['department', 'shift', 'gracePolicy'])
            ->where('employment_status', 'active')
            ->whereDoesntHave('role', fn ($q) => $q->where('name', 'director'))
            ->orderBy('first_name')
            ->get();
    }
}
