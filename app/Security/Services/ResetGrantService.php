<?php

namespace App\Security\Services;

use App\Security\Models\SecurityResetGrant;
use App\Security\Support\ClientIpResolver;
use Illuminate\Http\Request;

/**
 * Single-use, short-lived proof that an out-of-band OTP was verified.
 *
 * Password resets and OTP-based signups MUST present a grant issued by a
 * successful verification on the same identifier and client. This is the
 * control that closes the unauthenticated account-takeover / token-minting
 * chain: knowing a victim's e-mail address is no longer sufficient.
 *
 * Nothing sensitive is stored: identifiers and client fingerprints are
 * HMAC-SHA256 digests keyed by APP_KEY.
 */
final class ResetGrantService
{
    public function __construct(private readonly ClientIpResolver $resolver) {}

    /**
     * Called ONLY after an OTP was verified successfully.
     */
    public function issue(string $type, string $identifier, Request $request): ?SecurityResetGrant
    {
        $ttl = max(1, (int) config('security.reset_grants.ttl_minutes', 15));

        try {
            // One live grant per (type, identifier, client). Re-verifying
            // invalidates the previous grant instead of stacking them.
            SecurityResetGrant::query()
                ->where('type', $type)
                ->where('identifier_hash', $this->identifierHash($type, $identifier))
                ->where('client_hash', $this->clientHash($request))
                ->delete();

            return SecurityResetGrant::query()->create([
                'type'            => $type,
                'identifier_hash' => $this->identifierHash($type, $identifier),
                'client_hash'     => $this->clientHash($request),
                'ip'              => $this->resolver->resolve($request),
                'expires_at'      => now()->addMinutes($ttl),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Atomically consume a grant. Returns false when missing, expired,
     * replayed, or issued to a different client / identifier.
     */
    public function consume(string $type, string $identifier, Request $request): bool
    {
        try {
            $query = SecurityResetGrant::query()
                ->where('type', $type)
                ->where('identifier_hash', $this->identifierHash($type, $identifier))
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now());

            if (config('security.reset_grants.bind_to_client', true)) {
                $query->where('client_hash', $this->clientHash($request));
            }

            $grant = $query->orderByDesc('id')->first();
            if (! $grant) {
                return false;
            }

            // Conditional update makes concurrent replays lose the race.
            $consumed = SecurityResetGrant::query()
                ->whereKey($grant->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return $consumed === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasValid(string $type, string $identifier, Request $request): bool
    {
        try {
            $query = SecurityResetGrant::query()
                ->where('type', $type)
                ->where('identifier_hash', $this->identifierHash($type, $identifier))
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now());

            if (config('security.reset_grants.bind_to_client', true)) {
                $query->where('client_hash', $this->clientHash($request));
            }

            return $query->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Canonical, stable representation used by both issue() and consume(). */
    public function normaliseIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return mb_strtolower($identifier);
        }

        return (string) preg_replace('/\D+/', '', $identifier);
    }

    public function phoneIdentifier(string $phone, string $countryCode): string
    {
        return $this->normaliseIdentifier($countryCode).$this->normaliseIdentifier($phone);
    }

    private function identifierHash(string $type, string $identifier): string
    {
        return hash_hmac('sha256', $type.'|'.$this->normaliseIdentifier($identifier), $this->secret());
    }

    private function clientHash(Request $request): string
    {
        $fingerprint = implode('|', [
            $this->resolver->resolve($request),
            mb_substr((string) $request->userAgent(), 0, 200),
            (string) $request->headers->get('Accept-Language', ''),
        ]);

        return hash_hmac('sha256', $fingerprint, $this->secret());
    }

    private function secret(): string
    {
        $key = (string) config('app.key', '');

        return $key !== '' ? $key : 'security-reset-grant-fallback';
    }
}
