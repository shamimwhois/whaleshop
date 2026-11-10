<?php

namespace App\Security\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Resolves the real client IP without letting an attacker forge it.
 *
 * X-Forwarded-For is honoured ONLY when REMOTE_ADDR belongs to a configured
 * trusted proxy CIDR. Otherwise the socket peer address is used. This matters
 * because the whole audit trail, rate limiter and auto-block list key off it.
 */
final class ClientIpResolver
{
    /** @return string[] */
    private function trustedProxies(): array
    {
        return (array) config('security.trusted_proxies', []);
    }

    public function resolve(Request $request): string
    {
        $remote = (string) $request->server->get('REMOTE_ADDR', '0.0.0.0');
        $remote = $this->clean($remote);
        $trusted = $this->trustedProxies();

        if ($trusted === [] || ! $this->isTrusted($remote, $trusted)) {
            return $remote;
        }

        $forwarded = (string) $request->headers->get('X-Forwarded-For', '');
        if ($forwarded === '') {
            $forwarded = (string) $request->headers->get('X-Real-Ip', '');
        }

        // The right-most untrusted hop is the closest to the origin client.
        $chain = array_reverse(array_map('trim', explode(',', $forwarded)));
        foreach ($chain as $candidate) {
            $candidate = $this->clean($candidate);
            if ($candidate === '' || $this->isTrusted($candidate, $trusted)) {
                continue;
            }

            return $candidate;
        }

        return $remote;
    }

    public function isAllowlisted(string $ip): bool
    {
        foreach ((array) config('security.ip_allowlist', []) as $allowed) {
            if ($this->matches($ip, (string) $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function isTrusted(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->matches($ip, (string) $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $ip, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($cidr === '' || $cidr === '*') {
            return $cidr === '*';
        }

        if ($ip === $cidr) {
            return true;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // IpUtils handles plain IPs too, but guard against invalid CIDR input.
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        try {
            return IpUtils::checkIp($ip, $cidr);
        } catch (\Throwable) {
            return false;
        }
    }

    private function clean(string $ip): string
    {
        $ip = trim($ip);

        // Bracketed IPv6 with an optional port: [2001:db8::1]:443
        if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $ip, $m) === 1) {
            $ip = $m[1];
        } else {
            // Bare IPv4 with a port: 203.0.113.7:443
            $parts = explode(':', $ip);
            if (count($parts) === 2 && ctype_digit($parts[1])
                && filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $ip = $parts[0];
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }

        return substr(preg_replace('/[^0-9a-fA-F:.]/', '', $ip) ?? '', 0, 45);
    }
}
