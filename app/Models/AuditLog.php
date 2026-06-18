<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'resource_type', 'resource_id', 'provider', 'ip', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(string $action, array $attrs = []): self
    {
        return static::create(array_merge([
            'action'     => $action,
            'ip'         => request()?->ip(),
            'user_id'    => auth()->id(),
            'created_at' => now(),
        ], $attrs));
    }
}
