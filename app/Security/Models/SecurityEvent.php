<?php

namespace App\Security\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_type
 * @property string $severity
 * @property int $score
 * @property string $action
 * @property string $ip
 * @property array|null $categories
 */
class SecurityEvent extends Model
{
    protected $table = 'security_events';

    protected $fillable = [
        'event_type', 'severity', 'score', 'action', 'ip', 'method', 'path',
        'route_name', 'user_agent', 'user_id', 'categories', 'signatures',
        'context', 'status_code', 'fingerprint', 'hit_count', 'last_seen_at',
    ];

    protected $casts = [
        'categories'   => 'array',
        'signatures'   => 'array',
        'context'      => 'array',
        'score'        => 'integer',
        'user_id'      => 'integer',
        'status_code'  => 'integer',
        'hit_count'    => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function scopeSince(Builder $query, int $hours): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }
}
