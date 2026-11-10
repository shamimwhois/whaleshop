<?php

namespace App\Console\Commands;

use App\Security\Models\SecurityEvent;
use App\Security\Models\SecurityIpRule;
use App\Security\Models\SecurityResetGrant;
use Illuminate\Console\Command;

class SecurityPruneCommand extends Command
{
    protected $signature = 'security:prune
                            {--days= : Override SECURITY_RETENTION_DAYS}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Prune expired IDS events, IP rules and consumed OTP grants';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('security.retention_days', 90)));
        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');

        try {
            $events = SecurityEvent::query()->where('created_at', '<', $cutoff)->count();
            $grants = SecurityResetGrant::query()
                ->where(function ($q): void {
                    $q->whereNotNull('consumed_at')->orWhere('expires_at', '<', now());
                })->count();
            $rules = SecurityIpRule::query()
                ->where('expires_at', '<', now())
                ->whereNotNull('expires_at')
                ->count();

            $this->info("Retention window: {$days} day(s) (before {$cutoff->toDateTimeString()})");
            $this->line("  security_events      : {$events}");
            $this->line("  security_reset_grants: {$grants}");
            $this->line("  expired security_ip_rules: {$rules}");

            if ($dry) {
                $this->warn('Dry run - nothing was deleted.');

                return self::SUCCESS;
            }

            $deletedEvents = SecurityEvent::query()->where('created_at', '<', $cutoff)->delete();
            $deletedGrants = SecurityResetGrant::query()
                ->where(function ($q): void {
                    $q->whereNotNull('consumed_at')->orWhere('expires_at', '<', now());
                })->delete();
            $deletedRules = SecurityIpRule::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->delete();

            $this->info(sprintf(
                'Deleted %d events, %d grants and %d expired rules.',
                $deletedEvents,
                $deletedGrants,
                $deletedRules
            ));
        } catch (\Throwable $e) {
            $this->error('Prune failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
