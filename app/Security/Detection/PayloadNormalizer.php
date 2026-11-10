<?php

namespace App\Security\Detection;

/**
 * Produces de-obfuscated variants of an untrusted string so signatures are
 * matched against what an interpreter would actually see, not the raw bytes.
 *
 * Handles: percent/URL encoding (including double encoding), HTML entities,
 * numeric HTML entities, NUL/control-byte injection, Unicode fullwidth and
 * confusable characters, SQL inline comments used to split keywords, and
 * whitespace padding.
 */
final class PayloadNormalizer
{
    private const MAX_LENGTH = 8192;

    /** @var array<string, string> */
    private array $confusables = [
        "\xE2\x80\x8B" => '', // zero width space
        "\xE2\x80\x8C" => '',
        "\xE2\x80\x8D" => '',
        "\xEF\xBB\xBF" => '',
        "\xEF\xBC\x88" => '(', // fullwidth (
        "\xEF\xBC\x89" => ')',
        "\xEF\xBC\xBB" => '[',
        "\xEF\xBC\xBD" => ']',
        "\xEF\xBC\xB4" => ';',
        "\xEF\xBC\x8F" => '/',
        "\xEF\xBC\x9C" => '<',
        "\xEF\xBC\x9E" => '>',
        "\xEF\xBC\x87" => "'",
        "\xEF\xBC\x82" => '"',
        "\xEF\xBC\x85" => '%',
        "\xEF\xBC\x83" => '#',
        "\xEF\xBC\x8B" => '+',
        "\xE2\x80\x98" => "'",
        "\xE2\x80\x99" => "'",
        "\xE2\x80\x9C" => '"',
        "\xE2\x80\x9D" => '"',
    ];

    /**
     * @return string[] de-obfuscated variants, most-derived last
     */
    public function variants(string $value, int $passes = 3): array
    {
        $value = substr($value, 0, self::MAX_LENGTH);

        $variants = [];
        $current  = $value;

        for ($i = 0; $i < max(1, $passes); $i++) {
            $decoded = $this->decodeOnce($current);
            $variants[] = $decoded;

            if ($decoded === $current) {
                break;
            }
            $current = $decoded;
        }

        // Collapse common SQL keyword-splitting and whitespace padding tricks.
        $collapsed = $this->collapseEvasions(end($variants) ?: $value);
        if ($collapsed !== end($variants)) {
            $variants[] = $collapsed;
        }

        return array_values(array_unique($variants));
    }

    public function normalize(string $value, int $passes = 3): string
    {
        $variants = $this->variants($value, $passes);

        return (string) end($variants);
    }

    private function decodeOnce(string $value): string
    {
        // Strip NUL bytes and other C0 controls used to bypass WAF regexes.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        $value = strtr($value, $this->confusables);

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = rawurldecode($value);
        $value = urldecode($value);

        // Numeric / hex HTML entities left over after double encoding.
        $value = preg_replace_callback(
            '/&#(?:x([0-9a-fA-F]+)|([0-9]+));?/',
            static function (array $m): string {
                $code = isset($m[1]) && $m[1] !== '' ? hexdec($m[1]) : (int) ($m[2] ?? 0);

                return $code > 0 && $code < 0x110000 ? mb_chr((int) $code, 'UTF-8') : '';
            },
            $value
        ) ?? $value;

        // Normalise Unicode look-alikes (fullwidth ASCII -> ASCII).
        if (function_exists('normalizer_normalize')) {
            $nfkc = normalizer_normalize($value, \Normalizer::FORM_KC);
            if (is_string($nfkc) && $nfkc !== '') {
                $value = $nfkc;
            }
        }

        // Collapse all whitespace runs to a single space for keyword matching.
        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }

    private function collapseEvasions(string $value): string
    {
        // "un/**/ion" -> "union", "SEL/**/ECT" -> "SELECT"
        $value = preg_replace('#/\*(?:[^*]|\*(?!/))*\*/#s', '', $value) ?? $value;
        // "u n i o n" style padding between single letters
        $value = preg_replace('/(?:\b\w\s){3,}\b\w\b/u', '', $value) ?? $value;
        // "OR 1=1 --" comment terminators are meaningful, keep them but strip
        // repeated dashes so "-- -" is still seen as a SQL comment.
        $value = preg_replace('/-{2,}\s*-+/', '--', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', trim($value)) ?? $value;
    }
}
