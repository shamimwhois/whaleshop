<?php

namespace App\Security\Detection;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Inspects every untrusted surface of a request and returns a scored report.
 *
 * The inspector never throws: a malformed/oversized request is itself a
 * signal, not a reason to take the application down.
 */
final class RequestInspector
{
    private SignatureSet $signatures;
    private PayloadNormalizer $normalizer;

    public function __construct(?SignatureSet $signatures = null, ?PayloadNormalizer $normalizer = null)
    {
        $this->signatures = $signatures ?? new SignatureSet();
        $this->normalizer = $normalizer ?? new PayloadNormalizer();
    }

    public function inspect(Request $request): DetectionReport
    {
        $report = new DetectionReport();
        $cfg    = (array) config('security.detection', []);
        $limit  = (int) ($cfg['max_payload_bytes'] ?? 32768);
        $maxItems = (int) ($cfg['max_input_items'] ?? 250);
        $depth    = (int) ($cfg['max_depth'] ?? 5);
        $passes   = (int) ($cfg['decode_passes'] ?? 3);

        $targets = $this->collectTargets($request);
        $scanned = 0;
        $truncated = false;

        foreach ($targets as $field => $raw) {
            if (! is_string($raw) || $raw === '') {
                continue;
            }

            if (++$scanned > $maxItems) {
                $truncated = true;
                break;
            }

            if (strlen($raw) > $limit) {
                $truncated = true;
                $raw = substr($raw, 0, $limit);
            }

            foreach ($this->normalizer->variants($raw, $passes) as $variant) {
                foreach ($this->signatures->all() as $rule) {
                    if (preg_match($rule['pattern'], $variant) === 1) {
                        $report->add(new DetectionResult(
                            $rule['category'],
                            $rule['rule'],
                            (string) $field,
                            (int) $rule['weight'],
                            $this->excerpt($field, $raw),
                        ));
                    }
                }
            }
        }

        $this->inspectUploads($request, $report);
        $this->inspectProtocol($request, $report, $truncated, $depth);

        return $report;
    }

    /**
     * @return array<string, string>
     */
    private function collectTargets(Request $request): array
    {
        $targets = [];

        // Path + decoded path (route parameters are part of the URL).
        $path = $request->path();
        $targets['__path'] = $path;
        $targets['__query'] = (string) $request->getQueryString();

        foreach ($request->query() as $key => $value) {
            $this->flatten('query.'.$key, $value, $targets, 0);
        }

        foreach ($request->request->all() as $key => $value) {
            $this->flatten('body.'.$key, $value, $targets, 0);
        }

        if ($request->isJson()) {
            $json = $request->json()->all();
            foreach ($json as $key => $value) {
                $this->flatten('json.'.$key, $value, $targets, 0);
            }
        }

        $headers = [
            'User-Agent', 'Referer', 'Host', 'X-Forwarded-For', 'X-Forwarded-Host',
            'X-Real-Ip', 'X-Original-Url', 'X-Rewrite-Url', 'Content-Type',
            'Accept', 'Origin',
        ];
        foreach ($headers as $header) {
            $value = $request->headers->get($header);
            if (is_string($value)) {
                $targets['header.'.$header] = $value;
            }
        }

        foreach ($request->cookies->all() as $key => $value) {
            if (is_string($value)) {
                $targets['cookie.'.$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }

        return $targets;
    }

    private function flatten(string $prefix, mixed $value, array &$out, int $level): void
    {
        $maxDepth = (int) config('security.detection.max_depth', 5);
        if ($level > $maxDepth) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->flatten($prefix.'.'.$key, $item, $out, $level + 1);
            }
            return;
        }

        if (is_string($value) || is_numeric($value)) {
            $out[$prefix] = (string) $value;
        }
    }

    private function inspectUploads(Request $request, DetectionReport $report): void
    {
        foreach ($request->allFiles() as $key => $files) {
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $name = $file->getClientOriginalName();
                foreach ($this->matchValue('file.'.$key.'.name', $name) as $match) {
                    $report->add($match);
                }

                // MIME / extension mismatch (e.g. PHP renamed to .png).
                $ext = strtolower($file->getClientOriginalExtension());
                $mime = strtolower((string) $file->getMimeType());
                if (($ext !== '' && str_contains($mime, 'php'))
                    || in_array($ext, ['php', 'phtml', 'phar', 'pht', 'exe', 'sh'], true)) {
                    $report->add(new DetectionResult(
                        'upload', 'executable-upload', 'file.'.$key, 80, '[filename redacted]'
                    ));
                }

                // Inspect the first bytes of small text files for embedded PHP.
                if ($file->isValid() && $file->getSize() > 0 && $file->getSize() < 262144) {
                    $head = @file_get_contents($file->getRealPath(), false, null, 0, 4096);
                    if (is_string($head) && preg_match('/<\?php|<\?=|<\s*script\b/i', $head) === 1) {
                        $report->add(new DetectionResult(
                            'upload', 'code-in-upload', 'file.'.$key, 85, '[file content redacted]'
                        ));
                    }
                }
            }
        }
    }

    /**
     * @return DetectionResult[] (spread into add(); empty means no match)
     */
    private function matchValue(string $field, string $value): array
    {
        $found = [];
        foreach ($this->normalizer->variants($value) as $variant) {
            foreach ($this->signatures->all() as $rule) {
                if (in_array($rule['category'], ['upload'], true) && preg_match($rule['pattern'], $variant) === 1) {
                    $found[] = new DetectionResult(
                        $rule['category'], $rule['rule'], $field, (int) $rule['weight'], '[filename redacted]'
                    );
                }
            }
        }

        return $found ? [$found[0]] : [];
    }

    private function inspectProtocol(Request $request, DetectionReport $report, bool $truncated, int $depth): void
    {
        if ($truncated) {
            $report->add(new DetectionResult('anomaly', 'oversized-payload', '__request', 20, '[truncated]'));
        }

        // Control bytes / NUL in the raw request line.
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $request->path().$request->getQueryString()) === 1) {
            $report->add(new DetectionResult('anomaly', 'control-bytes', '__path', 35));
        }

        // CRLF injected into a header value.
        foreach (['User-Agent', 'Referer', 'Host', 'X-Forwarded-For'] as $header) {
            $value = (string) $request->headers->get($header);
            if (preg_match('/[\r\n]/', $value) === 1) {
                $report->add(new DetectionResult('anomaly', 'header-crlf', 'header.'.$header, 60));
            }
        }

        // Host header that does not match the configured application URL.
        $expected = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $request->getHost();
        if ($expected && $host && strtolower($expected) !== strtolower($host)
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $report->add(new DetectionResult('anomaly', 'host-mismatch', 'header.Host', 25));
        }
    }

    private function excerpt(string $field, string $value): string
    {
        $lower = strtolower($field);
        foreach ((array) config('security.redact_keys', []) as $sensitive) {
            if (str_contains($lower, strtolower((string) $sensitive))) {
                return '[REDACTED]';
            }
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return mb_substr($value, 0, 160);
    }
}
