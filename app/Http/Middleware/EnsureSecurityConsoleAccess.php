<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extra authorisation gate for the security console. Permission middleware
 * alone is not enough: the ADMIN role owns every permission, and a leaked
 * manager token should never be able to read or tamper with the audit trail.
 */
final class EnsureSecurityConsoleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.console.enabled', true)) {
            abort(404);
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $allowed = array_map('intval', (array) config('security.console.role_ids', [1]));
        $hasRole = method_exists($user, 'roles')
            && $user->roles->contains(
                static fn ($role): bool => in_array((int) $role->id, $allowed, true)
            );

        if (! $hasRole) {
            abort(403, 'Security console is restricted to administrators.');
        }

        return $next($request);
    }
}
