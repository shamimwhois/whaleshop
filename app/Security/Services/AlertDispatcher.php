<?php

namespace App\Security\Services;

use App\Security\Models\SecurityEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fans high-severity incidents out to a dedicated log channel, an optional
 * signed webhook and an optional mailbox. All transports are best-effort.
 */
final class AlertDispatcher
{
    private const RANK = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function dispatch(SecurityEvent $event): void
    {
        if (! config('security.alerts.enabled', true)) {
            return;
        }

        $min = (string) config('security.alerts.min_severity', 'high');
        if ((self::RANK[$event->severity] ?? 0) < (self::RANK[$min] ?? 3)) {
            return;
        }

        if (! $this->reserve($event)) {
            return; // deduplicated
        }

        $payload = [
            'id'         => $event->id,
            'type'       => $event->event_type,
            'severity'   => $event->severity,
            'score'      => $event->score,
            'action'     => $event->action,
            'ip'         => $event->ip,
            'method'     => $event->method,
            'path'       => $event->path,
            'user_id'    => $event->user_id,
            'categories' => $event->categories,
            'hits'       => $event->hit_count,
            'created_at' => optional($event->created_at)->toIso8601String(),
        ];

        $this->toLog($payload);
        $this->toWebhook($payload);
        $this->toMail($payload);
    }

    private function reserve(SecurityEvent $event): bool
    {
        $minutes = (int) config('security.alerts.dedupe_minutes', 15);
        $key = 'security:alert:'.hash('sha256', $event->event_type.'|'.$event->ip.'|'.$event->severity.'|'.($event->path ?? ''));

        try {
            return (bool) Cache::add($key, 1, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return true;
        }
    }

    private function toLog(array $payload): void
    {
        try {
            $channel = (string) config('security.log_channel', 'security');
            Log::channel($channel)->warning('SECURITY ALERT', $payload);
        } catch (\Throwable) {
            Log::warning('SECURITY ALERT (fallback channel)', $payload);
        }
    }

    private function toWebhook(array $payload): void
    {
        $url = (string) config('security.alerts.webhook_url', '');
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return;
        }

        try {
            $body = json_encode(['event' => 'security.incident', 'data' => $payload], JSON_THROW_ON_ERROR);
            $headers = ['Content-Type' => 'application/json'];
            $secret = (string) config('security.alerts.webhook_secret', '');
            if ($secret !== '') {
                $headers['X-Security-Signature'] = hash_hmac('sha256', $body, $secret);
            }

            Http::timeout((int) config('security.alerts.timeout', 3))
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable) {
            // Alert delivery must never affect the request lifecycle.
        }
    }

    private function toMail(array $payload): void
    {
        $to = (string) config('security.alerts.mail_to', '');
        if ($to === '') {
            return;
        }

        try {
            Mail::raw(
                "Severity: {$payload['severity']}\nType: {$payload['type']}\nIP: {$payload['ip']}\n"
                ."Path: {$payload['path']}\nCategories: ".implode(', ', (array) ($payload['categories'] ?? []))
                ."\nAction: {$payload['action']}",
                static function ($message) use ($to, $payload): void {
                    $message->to($to)->subject('[SECURITY] '.$payload['severity'].' incident on '.config('app.name'));
                }
            );
        } catch (\Throwable) {
            // no-op
        }
    }
}
