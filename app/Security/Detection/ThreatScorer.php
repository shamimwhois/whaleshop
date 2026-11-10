<?php

namespace App\Security\Detection;

use Illuminate\Http\Request;

/**
 * Turns raw signature hits into a threat score, severity and an action.
 *
 * Scoring model:
 *  - the strongest hit in a category counts in full;
 *  - additional hits in the SAME category are heavily discounted (prevents
 *    a single payload from inflating its own score);
 *  - multiple independent categories receive a correlation bonus.
 */
final class ThreatScorer
{
    private const SEVERITY_ORDER = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    public function score(DetectionReport $report, Request $request): DetectionReport
    {
        $cfg    = (array) config('security.detection', []);
        $bonus  = (array) ($cfg['scoring'] ?? []);
        $byCategory = [];

        foreach ($report->matches as $match) {
            $byCategory[$match->category][] = $match->weight;
        }

        $score = 0;
        foreach ($byCategory as $weights) {
            rsort($weights);
            $top = (int) ($weights[0] ?? 0);
            $extra = 0;
            foreach (array_slice($weights, 1) as $w) {
                $extra += (int) round($w * 0.15);
            }
            $score += $top + min($extra, 25);
        }

        $categories = array_keys($byCategory);
        if (count($categories) >= 2) {
            $score += (int) ($bonus['multi_vector_bonus'] ?? 20);
        }

        if ($this->isSensitiveRoute($request)) {
            $score += (int) ($bonus['sensitive_route_bonus'] ?? 10);
        }

        foreach ($report->matches as $match) {
            if ($match->category === 'scanner' && $match->rule === 'probe-path') {
                $score += (int) ($bonus['scanner_path_bonus'] ?? 25);
                break;
            }
        }

        foreach ($report->matches as $match) {
            if ($match->category === 'upload' && $match->weight >= 70) {
                $score += (int) ($bonus['upload_threat_bonus'] ?? 25);
                break;
            }
        }

        foreach ($report->matches as $match) {
            if ($match->rule === 'oversized-payload') {
                $score += (int) ($bonus['oversized_payload_bonus'] ?? 15);
                break;
            }
        }

        $score = max(0, min(200, $score));

        $severity = match (true) {
            $score >= 100 => 'critical',
            $score >= 70  => 'high',
            $score >= 40  => 'medium',
            $score >= 15  => 'low',
            default       => 'info',
        };

        $report->score      = $score;
        $report->severity   = $severity;
        $report->categories = array_fill_keys($categories, true);
        $report->action     = $this->action($score, $cfg);

        return $report;
    }

    public function isMoreSevere(string $candidate, string $threshold): bool
    {
        return (self::SEVERITY_ORDER[$candidate] ?? 0) >= (self::SEVERITY_ORDER[$threshold] ?? 0);
    }

    private function action(int $score, array $cfg): string
    {
        if (config('security.mode', 'enforce') !== 'enforce') {
            return $score >= (int) ($cfg['log_threshold'] ?? 15) ? 'log' : 'allow';
        }

        if ($score >= (int) ($cfg['block_threshold'] ?? 80)) {
            return 'block';
        }

        if ($score >= (int) ($cfg['challenge_threshold'] ?? 45)) {
            return 'challenge';
        }

        return $score >= (int) ($cfg['log_threshold'] ?? 15) ? 'log' : 'allow';
    }

    private function isSensitiveRoute(Request $request): bool
    {
        $path = trim($request->path(), '/');
        foreach ((array) config('security.behaviour.sensitive_paths', []) as $pattern) {
            $pattern = trim((string) $pattern, '/');
            if ($pattern === '' || $path === '') {
                continue;
            }
            $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#i';
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
