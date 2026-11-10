<?php

namespace App\Security\Services;

use App\Security\Models\SecurityEvent;
use App\Security\Models\SecurityIpRule;
use App\Security\Support\ClientIpResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Block / allow list plus escalating auto-blocking for repeat offenders.
 */
final class IpReputationService
{
    public function __construct(private readonly ClientIpResolver $resolver) {}

    public function isBlocked(string $ip): bool
    {
        return $this->activeBlock($ip) !== null;
    }

    public function activeBlock(string $ip): ?SecurityIpRule
    {
        if ($this->resolver->isAllowlisted($ip)) {
            return null;
        }

        try {
            /** @var SecurityIpRule|null $rule */
            $rule = SecurityIpRule::query()
                ->where('ip', $ip)
                ->where('type', 'block')
                ->active()
                ->first();

            if ($rule && ! $rule->isActive()) {
                return null;
            }

            return $rule;
        } catch (\Throwable) {
            // Table may not be migrated yet - fail open, never break traffic.
            return null;
        }
    }

    public function block(
        string $ip,
        string $reason = 'Manual block',
        ?int $minutes = null,
        string $source = 'manual',
        ?int $userId = null,
    ): ?SecurityIpRule {
        if ($this->resolver->isAllowlisted($ip)) {
            return null;
        }

        $minutes ??= (int) config('security.auto_block.duration_minutes', 60);

        try {
            /** @var SecurityIpRule $rule */
            $rule = SecurityIpRule::query()->updateOrCreate(
                ['ip' => $ip, 'type' => 'block'],
                [
                    'source'         => $source,
                    'reason'         => mb_substr($reason, 0, 500),
                    'expires_at'     => $minutes > 0 ? now()->addMinutes($minutes) : null,
                    'created_by'     => $userId,
                    'incident_count' => 1,
                ]
            );

            Cache::forget($this->blockCacheKey($ip));

            return $rule;
        } catch (\Throwable) {
            return null;
        }
    }

    public function unblock(string $ip): int
    {
        try {
            $deleted = SecurityIpRule::query()->where('ip', $ip)->where('type', 'block')->delete();
            Cache::forget($this->blockCacheKey($ip));
            Cache::forget($this->incidentCacheKey($ip));

            return $deleted;
        } catch (\Throwable) {
            return 0;
        }
    }

    public function allow(string $ip, ?string $reason = null): ?SecurityIpRule
    {
        try {
            /** @var SecurityIpRule $rule */
            $rule = SecurityIpRule::query()->updateOrCreate(
                ['ip' => $ip, 'type' => 'allow'],
                ['source' => 'manual', 'reason' => $reason, 'expires_at' => null]
            );
            $this->unblock($ip);

            return $rule;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Escalate repeat high-severity offenders into a temporary auto-ban.
     */
    public function noteIncident(string $ip, string $reason, int $score = 0): ?SecurityIpRule
    {
        if (! config('security.auto_block.enabled', true) || $this->resolver->isAllowlisted($ip)) {
            return null;
        }

        $key = $this->incidentCacheKey($ip);
        $window = (int) config('security.auto_block.window_minutes', 15);

        try {
            if (! Cache::has($key)) {
                Cache::add($key, 0, now()->addMinutes($window));
            }
            $count = (int) Cache::increment($key);
        } catch (\Throwable) {
            return null;
        }

        $threshold = (int) config('security.auto_block.incident_threshold', 3);
        if ($count < $threshold) {
            return null;
        }

        $base = (int) config('security.auto_block.duration_minutes', 60);
        $minutes = config('security.auto_block.escalating', true)
            ? min($base * (1 + intdiv(max(0, $count - $threshold), $threshold)), 1440)
            : $base;

        $rule = $this->block($ip, 'Auto-block after '.$count.' incidents: '.$reason, $minutes, 'auto');
        Cache::forget($key);

        return $rule;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeRules(): array
    {
        try {
            return SecurityIpRule::query()
                ->active()
                ->orderByDesc('id')
                ->limit(200)
                ->get()
                ->map(static fn (SecurityIpRule $r): array => [
                    'id'             => $r->id,
                    'ip'             => $r->ip,
                    'type'           => $r->type,
                    'source'         => $r->source,
                    'reason'         => $r->reason,
                    'incident_count' => $r->incident_count,
                    'expires_at'     => optional($r->expires_at)->toIso8601String(),
                    'created_at'     => optional($r->created_at)->toIso8601String(),
                ])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, int> */
    public function topOffenders(int $hours = 24, int $limit = 10): array
    {
        try {
            return SecurityEvent::query()
                ->since($hours)
                ->whereIn('severity', ['high', 'critical'])
                ->selectRaw('ip, COUNT(*) as total')
                ->groupBy('ip')
                ->orderByDesc('total')
                ->limit($limit)
                ->pluck('total', 'ip')
                ->map(static fn ($v): int => (int) $v)
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function blockCacheKey(string $ip): string
    {
        return 'security:block:'.hash('sha256', $ip);
    }

    private function incidentCacheKey(string $ip): string
    {
        return 'security:incidents:'.hash('sha256', $ip);
    }
}
