<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',  // Add this
        'status',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Role key => dashboard route. The single source for role-based redirects.
     */
    public const DASHBOARD_ROUTES = [
        'administrator' => 'admin.dashboard',
        'director' => 'director.dashboard',
        'manager' => 'manager.dashboard',
        'line_manager' => 'line_manager.dashboard',
        'employee' => 'employee.dashboard',
    ];

    /**
     * Lower-cased role name from the database, e.g. "Line_manager" → "line_manager".
     */
    public function roleKey(): string
    {
        return strtolower((string) $this->role?->name);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleKey(), array_map('strtolower', $roles), true);
    }

    public function isActive(): bool
    {
        return strtolower((string) ($this->status ?? 'active')) === 'active';
    }

    /**
     * Dashboard route for this user's role, or null when the role is unknown.
     */
    public function dashboardRoute(): ?string
    {
        return self::DASHBOARD_ROUTES[$this->roleKey()] ?? null;
    }
}
