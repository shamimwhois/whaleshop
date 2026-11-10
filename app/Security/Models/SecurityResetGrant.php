<?php

namespace App\Security\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-use proof that an out-of-band OTP was actually verified.
 * Never stores the raw identifier or the OTP itself.
 *
 * @property int $id
 * @property string $type
 * @property string $identifier_hash
 * @property string|null $client_hash
 * @property \Illuminate\Support\Carbon $expires_at
 */
class SecurityResetGrant extends Model
{
    protected $table = 'security_reset_grants';

    protected $fillable = [
        'type', 'identifier_hash', 'client_hash', 'ip', 'expires_at', 'consumed_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
