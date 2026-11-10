<?php

namespace App\Security\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ip
 * @property string $type
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class SecurityIpRule extends Model
{
    protected $table = 'security_ip_rules';

    protected $fillable = [
        'ip', 'type', 'source', 'reason', 'incident_count', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'incident_count' => 'integer',
        'expires_at'     => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(static function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
