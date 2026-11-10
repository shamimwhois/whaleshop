<?php

namespace App\Console\Commands;

use App\Security\Services\IpReputationService;
use App\Security\Support\ClientIpResolver;
use Illuminate\Console\Command;

class SecurityIpCommand extends Command
{
    protected $signature = 'security:ip
                            {action : block | unblock | allow | list | check}
                            {ip? : IPv4 or IPv6 address}
                            {--minutes= : Block duration; omit for the configured default}
                            {--reason= : Reason stored with the rule}
                            {--permanent : Never expire this block}';

    protected $description = 'Manage IDS IP block / allow rules';

    public function handle(IpReputationService $reputation, ClientIpResolver $resolver): int
    {
        $action = strtolower((string) $this->argument('action'));
        $ip = trim((string) ($this->argument('ip') ?? ''));

        if ($action === 'list') {
            $rules = $reputation->activeRules();
            $this->table(
                ['IP', 'Type', 'Source', 'Reason', 'Expires'],
                array_map(static fn (array $r): array => [
                    $r['ip'], $r['type'], $r['source'], $r['reason'], $r['expires_at'] ?? 'never',
                ], $rules) ?: [['(no active rules)', '', '', '', '']]
            );

            return self::SUCCESS;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->error('A valid IPv4/IPv6 address is required.');

            return self::INVALID;
        }

        if ($resolver->isAllowlisted($ip) && in_array($action, ['block', 'check'], true)) {
            $this->warn($ip.' is on the allowlist (SECURITY_IP_ALLOWLIST); block rules are not applied to it.');
        }

        switch ($action) {
            case 'check':
                $rule = $reputation->activeBlock($ip);
                $this->line($rule
                    ? "BLOCKED: {$rule->reason} (expires: ".($rule->expires_at?->toIso8601String() ?? 'never').')'
                    : 'ALLOWED: no active block for '.$ip);

                return self::SUCCESS;

            case 'block':
                $minutes = $this->option('permanent') ? 0 : (int) ($this->option('minutes') ?: config('security.auto_block.duration_minutes', 60));
                $reason = (string) ($this->option('reason') ?: 'Manual block via security:ip');
                $rule = $reputation->block($ip, $reason, $minutes, 'manual');
                if (! $rule) {
                    $this->error('Could not create the rule (IP may be allowlisted or the table is missing).');

                    return self::FAILURE;
                }
                $this->info("Blocked {$ip} for ".($minutes > 0 ? $minutes.' minute(s)' : 'an indefinite period').'.');

                return self::SUCCESS;

            case 'unblock':
                $deleted = $reputation->unblock($ip);
                $this->info("Removed {$deleted} block rule(s) for {$ip}.");

                return self::SUCCESS;

            case 'allow':
                if ($reputation->allow($ip, (string) ($this->option('reason') ?: 'Manual allowlist via security:ip'))) {
                    $this->info("{$ip} is now allowlisted and exempt from auto-blocking.");

                    return self::SUCCESS;
                }
                $this->error('Could not allowlist the IP.');

                return self::FAILURE;

            default:
                $this->error('Unknown action. Use: block, unblock, allow, list, check.');

                return self::INVALID;
        }
    }
}
