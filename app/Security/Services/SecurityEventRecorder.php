<?php

namespace App\Security\Services;

use App\Security\Detection\DetectionReport;
use App\Security\Models\SecurityEvent;
use App\Security\Support\ClientIpResolver;
use App\Security\Support\SensitiveDataRedactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central audit sink for the IDS.
 *
 * Responsibilities:
 *  - redact every persisted value (credentials/tokens/PII never hit the DB);
 *  - collapse repeated identical incidents into one row with a hit counter;
 *  - escalate severity when an already-known attack gets worse;
 *  - fan out high-severity incidents to alerts and auto-blocking;
 *  - never, ever break the request because security logging failed.
 */
final class SecurityEventRecorder
{
    private const RANK = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    private const LEVEL = [
        'info'     => 'info',
        'low'      => 'notice',
        'medium'   => 'warning',
        'high'     => 'error',
        'critical' => 'critical',
    ];

    public function __construct(
        private readonly ClientIpResolver $resolver,
        private readonly SensitiveDataRedactor $redactor,
        private readonly AlertDispatcher $alerts,
        private readonly IpReputationService $reputation,
    ) {}

    /**
     * @param array<string, mixed> $extra Free-form context; redacted before storage.
     */
    public function record(
        Request $request,
        string $eventType,
        string $severity = 'info',
        int $score = 0,
        string $action = 'log',
        array $extra = [],
        ?int $statusCode = null,
    ): ?SecurityEvent {
        $ip = (string) ($request->attributes->get('security_ip') ?: $this->resolver->resolve($request));
        $path = mb_substr((string) $request->path(), 0, 500);
        $severity = isset(self::RANK[$severity]) ? $severity : 'info';
        $fingerprint = hash('sha256', implode('|', [$eventType, $ip, $path, $severity]));

        $context = array_merge(
            [
                'reason'  => isset($extra['reason']) ? mb_substr((string) $extra['reason'], 0, 300) : null,
                'request' => config('security.recording.persist_context', true)
                    ? $this->redactor->requestContext($request)
                    : null,
            ],
            $this->redactor->clean($extra)
        );

        $signatures = $this->redactor->clean($extra['signatures'] ?? []);
        $categories = array_values(array_unique((array) ($extra['categories'] ?? [])));

        $attributes = [
            'event_type'  => mb_substr($eventType, 0, 60),
            'severity'    => $severity,
            'score'       => max(0, min(200, $score)),
            'action'      => $action,
            'ip'          => mb_substr($ip, 0, 45),
            'method'      => mb_substr((string) $request->method(), 0, 10),
            'path'        => $path,
            'route_name'  => mb_substr((string) $request->route()?->getName(), 0, 150),
            'user_agent'  => mb_substr((string) $request->userAgent(), 0, 1000),
            'user_id'     => $request->user()?->getAuthIdentifier(),
            'categories'  => $categories,
            'signatures'  => $signatures,
            'context'     => $context,
            'status_code' => $statusCode,
            'fingerprint' => $fingerprint,
        ];

        $this->toLog($attributes);

        try {
            $event = $this->persist($attributes);
        } catch (\Throwable $e) {
            Log::channel((string) config('security.log_channel', 'security'))
                ->error('Security event persistence failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $event) {
            return null;
        }

        if ($this->shouldAlert($event)) {
            $this->alerts->dispatch($event);
        }

        if (in_array($event->severity, ['high', 'critical'], true)) {
            $this->reputation->noteIncident($event->ip, $event->event_type, (int) $event->score);
        }

        return $event;
    }

    /**
     * Convenience wrapper for signature-based detections.
     */
    public function recordDetection(Request $request, DetectionReport $report, ?int $statusCode = null): ?SecurityEvent
    {
        if (! $report->hasMatches()) {
            return null;
        }

        $signatures = array_map(
            static fn ($m): array => [
                'category' => $m->category,
                'rule'     => $m->rule,
                'field'    => $m->field,
                'weight'   => $m->weight,
                'excerpt'  => $m->excerpt,
            ],
            array_slice($report->matches, 0, 40)
        );

        return $this->record(
            $request,
            'signature.detection',
            $report->severity,
            $report->score,
            $report->action,
            [
                'categories' => $report->categoryList(),
                'signatures' => $signatures,
                'reason'     => 'Matched '.count($report->matches).' attack signature(s)',
            ],
            $statusCode,
        );
    }

    private function persist(array $attributes): ?SecurityEvent
    {
        $window = max(1, (int) config('security.recording.dedupe_minutes', 10));

        /** @var SecurityEvent|null $existing */
        $existing = SecurityEvent::query()
            ->where('fingerprint', $attributes['fingerprint'])
            ->where('created_at', '>=', now()->subMinutes($window))
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->increment('hit_count');
            $existing->last_seen_at = now();
            $existing->status_code = $attributes['status_code'] ?? $existing->status_code;
            $existing->score = max((int) $existing->score, $attributes['score']);
            if ((self::RANK[$attributes['severity']] ?? 0) > (self::RANK[$existing->severity] ?? 0)) {
                $existing->severity = $attributes['severity'];
            }
            $existing->action = $attributes['action'];
            $existing->categories = $attributes['categories'];
            $existing->signatures = $attributes['signatures'];
            $existing->saveQuietly();

            return $existing->refresh();
        }

        $attributes['hit_count'] = 1;
        $attributes['last_seen_at'] = now();

        return SecurityEvent::query()->create($attributes);
    }

    private function shouldAlert(SecurityEvent $event): bool
    {
        $hits = (int) $event->hit_count;

        // New incident, or a meaningful escalation of a repeated one.
        return $hits === 1 || in_array($hits, [5, 20, 100, 500], true);
    }

    private function toLog(array $attributes): void
    {
        try {
            Log::channel((string) config('security.log_channel', 'security'))
                ->log(
                    self::LEVEL[$attributes['severity']] ?? 'info',
                    'IDS '.$attributes['event_type'],
                    [
                        'ip'         => $attributes['ip'],
                        'severity'   => $attributes['severity'],
                        'score'      => $attributes['score'],
                        'action'     => $attributes['action'],
                        'method'     => $attributes['method'],
                        'path'       => $attributes['path'],
                        'categories' => $attributes['categories'],
                        'reason'     => $attributes['context']['reason'] ?? null,
                    ]
                );
        } catch (\Throwable) {
            // Logging must never take the application down.
        }
    }
}
