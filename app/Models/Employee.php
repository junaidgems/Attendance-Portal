<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cnic',
        'department_id',
        // ❌ 'shift_id' HATAO
        // ❌ 'grace_policy_id' HATAO
        'role_id',
        'reports_to_id',
        'job_title',
        'joining_date',
        'date_of_birth',
        'gender',
        'address',
        'emergency_contact',
        'employment_status',
    ];

    // ❌ shift() relationship HATAO
    // ❌ gracePolicy() relationship HATAO

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'reports_to_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'reports_to_id');
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}