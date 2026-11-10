<?php

namespace App\Security\Support;

/**
 * Removes credentials, tokens and PII from anything persisted by the IDS.
 * The detector still sees the raw value; only the audit record is redacted.
 */
final class SensitiveDataRedactor
{
    private const MASK = '[REDACTED]';

    /** @var string[] */
    private array $keys;

    public function __construct()
    {
        $this->keys = array_map('strtolower', (array) config('security.redact_keys', []));
    }

    public function clean(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
        if ($depth > 6) {
            return '[TRUNCATED]';
        }

        if ($key !== null && $this->isSensitive($key)) {
            return self::MASK;
        }

        if (is_array($value)) {
            $out = [];
            $count = 0;
            foreach ($value as $k => $v) {
                if (++$count > 100) {
                    $out['__truncated'] = true;
                    break;
                }
                $out[$k] = $this->clean($v, $depth + 1, is_string($k) ? $k : null);
            }

            return $out;
        }

        if (is_string($value)) {
            return $this->cleanString($value);
        }

        if ($value instanceof \Stringable) {
            return $this->cleanString((string) $value);
        }

        if (is_object($value)) {
            return '[OBJECT '.class_basename($value).']';
        }

        return $value;
    }

    private function cleanString(string $value): string
    {
        // Never persist full bearer tokens or long opaque credentials even if
        // the key name was not on the allow-list.
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._\-]{12,}/i', 'Bearer '.self::MASK, $value) ?? $value;
        $value = preg_replace('/\b(?:eyJ[A-Za-z0-9_\-]{20,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,})\b/', self::MASK, $value) ?? $value;
        $value = preg_replace('/[A-Za-z0-9]{40,}\|[A-Za-z0-9]{20,}/', self::MASK, $value) ?? $value;

        // Common payment-card-like sequences.
        $value = preg_replace('/\b(?:\d[ -]*?){13,19}\b/', self::MASK, $value) ?? $value;

        return mb_substr($value, 0, 500);
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->keys as $sensitive) {
            if ($sensitive !== '' && str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function requestContext(\Illuminate\Http\Request $request): array
    {
        $payload = array_merge($request->query(), $request->request->all());
        if ($request->isJson()) {
            $payload = array_merge($payload, $request->json()->all());
        }

        return [
            'query' => $this->clean($request->query()),
            'body'  => $this->clean($request->request->all()),
            'json'  => $request->isJson() ? $this->clean($request->json()->all()) : null,
            'files' => array_map(
                static fn ($f) => is_array($f)
                    ? array_map(static fn ($x) => '[FILE]', $f)
                    : '[FILE]',
                $request->allFiles()
            ),
        ];
    }
}
