<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GracePolicy extends Model
{
    protected $fillable = ['name', 'late_grace_minutes', 'early_grace_minutes', 'is_default'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
