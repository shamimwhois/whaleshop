<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Security\Models\SecurityEvent;
use App\Security\Services\IpReputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Read-only security operations console + IP block management.
 *
 * Access is deliberately double-gated: the `settings` permission AND an
 * ADMIN role id from config('security.console.role_ids'). A token minted for
 * a customer therefore cannot read the audit trail even if a permission
 * seeder were misconfigured.
 */
class SecurityDashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:settings')];
    }

    public function overview(IpReputationService $reputation): JsonResponse
    {
        $hours = 24;
        $events = SecurityEvent::query()->since($hours)->get([
            'id', 'severity', 'score', 'action', 'event_type', 'ip', 'hit_count', 'created_at',
        ]);

        $bySeverity = ['info' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        foreach ($events as $event) {
            $key = $event->severity;
            if (isset($bySeverity[$key])) {
                $bySeverity[$key] += (int) $event->hit_count;
            }
        }

        return response()->json([
            'data' => [
                'window_hours'    => $hours,
                'incident_rows'   => $events->count(),
                'total_hits'      => array_sum($bySeverity),
                'by_severity'     => $bySeverity,
                'top_offenders'   => $reputation->topOffenders($hours, 10),
                'active_ip_rules' => $reputation->activeRules(),
                'mode'            => config('security.mode', 'enforce'),
                'auto_block'      => (bool) config('security.auto_block.enabled', true),
            ],
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hours'    => ['sometimes', 'integer', 'min:1', 'max:720'],
            'severity' => ['sometimes', 'string', 'in:info,low,medium,high,critical'],
            'type'     => ['sometimes', 'string', 'max:60'],
            'ip'       => ['sometimes', 'string', 'max:45'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SecurityEvent::query()->since((int) ($validated['hours'] ?? 24));
        foreach (['severity', 'ip'] as $field) {
            if (! empty($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }
        if (! empty($validated['type'])) {
            $query->ofType($validated['type']);
        }

        $paginator = $query->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        // `context` is already redacted at write time, but do not expose raw
        // signature excerpts through the console either.
        $paginator->getCollection()->transform(static function (SecurityEvent $e): array {
            return [
                'id'          => $e->id,
                'event_type'  => $e->event_type,
                'severity'    => $e->severity,
                'score'       => $e->score,
                'action'      => $e->action,
                'ip'          => $e->ip,
                'method'      => $e->method,
                'path'        => $e->path,
                'status_code' => $e->status_code,
                'categories'  => $e->categories,
                'hit_count'   => $e->hit_count,
                'created_at'  => optional($e->created_at)->toIso8601String(),
                'last_seen_at'=> optional($e->last_seen_at)->toIso8601String(),
            ];
        });

        return response()->json($paginator);
    }

    public function ipRules(IpReputationService $reputation): JsonResponse
    {
        return response()->json(['data' => $reputation->activeRules()]);
    }

    public function block(Request $request, IpReputationService $reputation): JsonResponse
    {
        $validated = $request->validate([
            'ip'      => ['required', 'ip'],
            'minutes' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'reason'  => ['sometimes', 'string', 'max:500'],
        ]);

        $minutes = array_key_exists('minutes', $validated)
            ? (int) $validated['minutes']
            : (int) config('security.auto_block.duration_minutes', 60);

        $rule = $reputation->block(
            $validated['ip'],
            (string) ($validated['reason'] ?? 'Blocked from security console'),
            $minutes,
            'manual',
            $request->user()?->getAuthIdentifier()
        );

        if (! $rule) {
            return response()->json(['message' => 'IP could not be blocked (allowlisted or storage unavailable).'], 422);
        }

        return response()->json(['message' => 'IP blocked.', 'data' => [
            'ip'         => $rule->ip,
            'expires_at' => optional($rule->expires_at)->toIso8601String(),
        ]], 201);
    }

    public function unblock(Request $request, IpReputationService $reputation): JsonResponse
    {
        $validated = $request->validate(['ip' => ['required', 'ip']]);

        return response()->json([
            'message' => 'IP block removed.',
            'removed' => $reputation->unblock($validated['ip']),
        ]);
    }
}
