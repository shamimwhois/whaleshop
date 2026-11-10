<?php

/*
|--------------------------------------------------------------------------
| Security Monitoring & Intrusion Detection Configuration
|--------------------------------------------------------------------------
|
| All values are environment-driven so the system can be tuned without a
| code deploy. Nothing here stores secrets. Run `php artisan config:cache`
| after changing .env values.
|
*/

return [

    'enabled'     => (bool) env('SECURITY_MONITORING_ENABLED', true),

    /*
     * x-api-key is a build-time *identifier* shipped to the front-end bundle,
     * NOT an authentication secret. It is read through config() (so it keeps
     * working under config:cache) and compared in constant time. Real
     * authentication remains auth:sanctum on protected routes.
     */
    'api_key'     => (string) env('VITE_API_KEY', ''),

    /*
     * observe  -> detect and log, never block
     * enforce  -> detect, log and actively block/challenge hostile requests
     */
    'mode'        => env('SECURITY_MODE', 'enforce'),

    'log_channel' => env('SECURITY_LOG_CHANNEL', 'security'),

    'detection'   => [
        'block_threshold'     => (int) env('SECURITY_BLOCK_THRESHOLD', 80),
        'challenge_threshold' => (int) env('SECURITY_CHALLENGE_THRESHOLD', 45),
        'log_threshold'       => (int) env('SECURITY_LOG_THRESHOLD', 15),
        'max_payload_bytes'   => (int) env('SECURITY_MAX_PAYLOAD_BYTES', 32768),
        'max_input_items'     => (int) env('SECURITY_MAX_INPUT_ITEMS', 250),
        'max_depth'           => 5,
        'decode_passes'       => 3,

        // Requests to these paths are still logged for auth events but are not
        // signature-scanned (health checks, payment callbacks, installer).
        'ignore_paths'        => [
            'up',
            'sanctum/csrf-cookie',
            'payment/*',
            'install/*',
        ],

        // Categories that can be switched off without a deploy.
        'disabled_categories' => array_filter(
            explode(',', (string) env('SECURITY_DISABLED_CATEGORIES', ''))
        ),

        'scoring'             => [
            'multi_vector_bonus'       => 20,
            'sensitive_route_bonus'   => 10,
            'oversized_payload_bonus' => 15,
            'scanner_path_bonus'      => 25,
            'upload_threat_bonus'     => 25,
        ],
    ],

    /*
     * Behavioural detection. These counters are kept in the configured cache
     * store and are independent of Laravel's HTTP throttle limiter.
     */
    'rate_limits' => [
        'api'               => (int) env('API_RATE_LIMIT', 120),
        'auth_per_minute'   => (int) env('AUTH_RATE_LIMIT_PER_MINUTE', 10),
        'auth_per_hour'     => (int) env('AUTH_RATE_LIMIT_PER_HOUR', 60),
        'otp_per_minute'    => (int) env('OTP_RATE_LIMIT_PER_IDENTIFIER', 1),
        'otp_ip_per_minute' => (int) env('OTP_RATE_LIMIT_PER_IP', 10),
    ],

    'behaviour'   => [
        'window_minutes'            => 10,
        'failed_login_threshold'    => 5,
        'otp_failure_threshold'     => 5,
        'not_found_threshold'       => 25,
        'authorization_threshold'   => 10,
        'request_burst_threshold'   => 180,
        'sensitive_paths'           => [
            'api/auth/*',
            'api/refresh-token',
            'api/admin/setting/*',
            'api/admin/role/*',
            'api/admin/permission/*',
            'api/admin/language/*',
            'install/*',
        ],
    ],

    'auto_block'  => [
        'enabled'           => (bool) env('SECURITY_AUTO_BLOCK', true),
        'incident_threshold' => 3,   // high/critical incidents inside the window
        'window_minutes'    => 15,
        'duration_minutes'  => (int) env('SECURITY_BLOCK_MINUTES', 60),
        'escalating'        => true, // repeat offenders get progressively longer bans
    ],

    'recording'   => [
        // Repeated identical incidents inside this window collapse into one
        // row with an incremented hit_count instead of flooding the table.
        'dedupe_minutes'  => (int) env('SECURITY_DEDUPE_MINUTES', 10),
        'persist_context' => (bool) env('SECURITY_PERSIST_CONTEXT', true),
    ],

    'alerts'      => [
        'enabled'        => (bool) env('SECURITY_ALERTS_ENABLED', true),
        'min_severity'   => env('SECURITY_ALERT_MIN_SEVERITY', 'high'),
        'dedupe_minutes' => 15,
        'webhook_url'    => env('SECURITY_ALERT_WEBHOOK_URL'),
        'webhook_secret' => env('SECURITY_ALERT_WEBHOOK_SECRET'),
        'mail_to'        => env('SECURITY_ALERT_MAIL_TO'),
        'timeout'        => 3,
    ],

    'retention_days' => (int) env('SECURITY_RETENTION_DAYS', 90),

    /*
     * Client IP resolution. Only trust X-Forwarded-For when the deployment is
     * actually behind a known reverse proxy CIDR. Empty = trust REMOTE_ADDR
     * only (safe default, prevents IP spoofing of the block list / audit log).
     */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SECURITY_TRUSTED_PROXIES', ''))
    ))),

    'ip_allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SECURITY_IP_ALLOWLIST', '127.0.0.1,::1'))
    ))),

    /*
     * Keys whose VALUES must never be written to the security event log.
     * Detection still inspects the raw value; only persistence is redacted.
     */
    'redact_keys'  => [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'refresh_token', 'api_key', 'secret', 'client_secret',
        'authorization', 'x-api-key', 'cookie', 'remember_token',
        'otp', 'code', 'pin', 'cvv', 'cvc', 'card_number', 'card',
        'database_password', 'license_key', 'private_key', 'access_key',
    ],

    /*
     * Fields that legitimately contain HTML (Quill / rich text editors).
     * These are passed through the allow-list HTML sanitiser before they
     * reach a controller, closing the stored-XSS sink used with v-html.
     */
    'rich_text_fields' => [
        'description', 'details', 'note', 'review', 'reason',
        'shipping_and_return', 'short_description', 'content', 'body',
        'cookies_details', 'terms', 'privacy_policy', 'response', 'message',
    ],

    'headers'     => [
        'enabled'                  => (bool) env('SECURITY_HEADERS_ENABLED', true),
        'x_content_type_options'   => 'nosniff',
        'x_frame_options'          => 'SAMEORIGIN',
        'referrer_policy'          => 'strict-origin-when-cross-origin',
        'permissions_policy'       => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'hsts'                     => env('SECURITY_HSTS', 'max-age=31536000; includeSubDomains'),
        // Report-Only by default: tightens visibility without breaking the
        // existing Vue build. Flip SECURITY_CSP_ENFORCE=true after testing.
        'csp_report_only'          => ! (bool) env('SECURITY_CSP_ENFORCE', false),
        'csp'                      => env(
            'SECURITY_CSP',
            "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; "
            ."style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; "
            ."font-src 'self' data: https:; connect-src 'self' https:; "
            ."form-action 'self'; upgrade-insecure-requests"
        ),
    ],

    'console'     => [
        'enabled'      => (bool) env('SECURITY_CONSOLE_ENABLED', true),
        'permission'   => 'settings',
        'role_ids'     => [1], // ADMIN
    ],

    /*
     * One-time, short-lived proof that an OTP was actually verified. This is
     * what makes password resets non-replayable and non-bypassable.
     */
    'reset_grants' => [
        'ttl_minutes'    => 15,
        'bind_to_client' => true,
    ],

    /*
     * Static source scanner (php artisan security:scan).
     */
    'scan'        => [
        'paths'    => ['app', 'config', 'routes', 'bootstrap'],
        'patterns' => [
            'env(\'APP_DEBUG\') outside config' => 'env\(\s*[\'"]APP_DEBUG',
            'env() call in application code'    => 'env\(\s*[\'"]',
            'loose comparison on secrets'       => '==\s*\$?(request|config|env)',
            'raw SQL interpolation'             => '(whereRaw|selectRaw|DB::select)\s*\([^)]*\$',
            'dynamic class resolution'          => 'resolve\s*\(\s*\$|new\s+\$',
            'unserialise of request data'       => 'unserialize\s*\(',
            'dangerous shell execution'         => '\b(exec|shell_exec|system|passthru|proc_open)\s*\(',
            'rand() for security tokens'        => '\brand\s*\(',
        ],
    ],
];
