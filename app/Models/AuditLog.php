<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_name', 'category', 'action', 'detail', 'created_at',
    ];

    public static function record(string $category, string $action, string $detail = '', ?string $actorName = null)
    {
        static::create([
            'actor_id' => Auth::id(),
            'actor_name' => $actorName ?? (Auth::user()->name ?? 'System'),
            'category' => $category,
            'action' => $action,
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }
}
