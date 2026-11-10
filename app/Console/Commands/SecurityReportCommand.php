<?php

namespace App\Console\Commands;

use App\Security\Models\SecurityEvent;
use App\Security\Services\IpReputationService;
use Illuminate\Console\Command;

class SecurityReportCommand extends Command
{
    protected $signature = 'security:report
                            {--hours=24 : Look-back window in hours}
                            {--severity= : Only include this minimum severity}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Summarise intrusion-detection events, top offenders and active IP blocks';

    private const RANK = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function handle(IpReputationService $reputation): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $min = (string) ($this->option('severity') ?: '');

        try {
            $query = SecurityEvent::query()->since($hours);
            if ($min !== '' && isset(self::RANK[$min])) {
                $query->whereIn('severity', array_keys(array_filter(
                    self::RANK,
                    static fn (int $rank): bool => $rank >= self::RANK[$min]
                )));
            }

            $events = $query->get(['id', 'event_type', 'severity', 'score', 'action', 'ip', 'path', 'hit_count', 'created_at']);

            $bySeverity = [];
            $byType = [];
            foreach ($events as $event) {
                $bySeverity[$event->severity] = ($bySeverity[$event->severity] ?? 0) + (int) $event->hit_count;
                $byType[$event->event_type] = ($byType[$event->event_type] ?? 0) + (int) $event->hit_count;
            }
            ksort($bySeverity);
            arsort($byType);

            $report = [
                'window_hours'    => $hours,
                'unique_records'  => $events->count(),
                'total_hits'      => array_sum($bySeverity),
                'by_severity'     => $bySeverity,
                'by_event_type'   => array_slice($byType, 0, 20, true),
                'top_offenders'   => $reputation->topOffenders($hours, 10),
                'active_ip_rules' => $reputation->activeRules(),
                'recent_critical' => $events->filter(
                    static fn ($e): bool => in_array($e->severity, ['high', 'critical'], true)
                )->sortByDesc('created_at')->take(10)->map(static fn ($e): array => [
                    'type'       => $e->event_type,
                    'severity'   => $e->severity,
                    'score'      => $e->score,
                    'action'     => $e->action,
                    'ip'         => $e->ip,
                    'path'       => $e->path,
                    'hits'       => $e->hit_count,
                    'created_at' => optional($e->created_at)->toIso8601String(),
                ])->values()->all(),
            ];
        } catch (\Throwable $e) {
            $this->error('Unable to read security events: '.$e->getMessage());
            $this->warn('Run `php artisan migrate` to create the security tables.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info(sprintf('IDS report - last %d hour(s)', $hours));
        $this->newLine();
        $this->line('Unique incident records : '.$report['unique_records']);
        $this->line('Total hits (deduplicated): '.$report['total_hits']);

        $this->newLine();
        $this->info('Hits by severity');
        $this->table(['Severity', 'Hits'], array_map(
            static fn (string $k, int $v): array => [$k, $v],
            array_keys($bySeverity),
            array_values($bySeverity)
        ) ?: [['(none)', 0]]);

        $this->info('Top event types');
        $rows = [];
        foreach (array_slice($byType, 0, 10, true) as $type => $count) {
            $rows[] = [$type, $count];
        }
        $this->table(['Event', 'Hits'], $rows ?: [['(none)', 0]]);

        $this->info('Top offender IPs');
        $rows = [];
        foreach ($report['top_offenders'] as $ip => $count) {
            $rows[] = [$ip, $count];
        }
        $this->table(['IP', 'High/Critical events'], $rows ?: [['(none)', 0]]);

        $this->info('Active IP rules');
        $rows = [];
        foreach ($report['active_ip_rules'] as $rule) {
            $rows[] = [$rule['ip'], $rule['type'], $rule['source'], $rule['reason'], $rule['expires_at'] ?? 'never'];
        }
        $this->table(['IP', 'Type', 'Source', 'Reason', 'Expires'], $rows ?: [['(none)', '', '', '', '']]);

        if ($report['recent_critical'] !== []) {
            $this->newLine();
            $this->warn('Most recent high/critical incidents');
            $rows = [];
            foreach ($report['recent_critical'] as $e) {
                $rows[] = [$e['created_at'], $e['severity'], $e['type'], $e['ip'], $e['path'], $e['action']];
            }
            $this->table(['When', 'Severity', 'Type', 'IP', 'Path', 'Action'], $rows);
        }

        return self::SUCCESS;
    }
}
