<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline browser-side defences to every response, including the
 * Content-Security-Policy that neutralises injected inline scripts in the
 * Vue front-end. CSP is Report-Only by default so an existing SPA is not
 * broken on deployment; set SECURITY_CSP_ENFORCE=true after validation.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $headers = [
            'X-Content-Type-Options' => (string) config('security.headers.x_content_type_options', 'nosniff'),
            'X-Frame-Options'        => (string) config('security.headers.x_frame_options', 'SAMEORIGIN'),
            'Referrer-Policy'        => (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'),
            'Permissions-Policy'     => (string) config('security.headers.permissions_policy', ''),
        ];

        $csp = trim((string) config('security.headers.csp', ''));
        if ($csp !== '') {
            $name = config('security.headers.csp_report_only', true)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $headers[$name] = preg_replace('/\s+/', ' ', $csp) ?? $csp;
        }

        $hsts = trim((string) config('security.headers.hsts', ''));
        if ($hsts !== '' && ($request->isSecure() || (bool) config('security.headers.force_hsts', false))) {
            $headers['Strict-Transport-Security'] = $hsts;
        }

        foreach ($headers as $name => $value) {
            if ($value !== '') {
                $response->headers->set($name, $value);
            }
        }

        // Do not leak the framework / server version.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
