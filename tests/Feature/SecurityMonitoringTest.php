<?php

namespace Tests\Feature;

use App\Security\Models\SecurityEvent;
use App\Security\Models\SecurityIpRule;
use App\Security\Services\IpReputationService;
use App\Security\Services\ResetGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/__security_echo', static function (Request $request) {
            return response()->json(['received' => $request->all()]);
        });
        Route::get('/__security_echo', static fn () => response()->json(['ok' => true]));

        config()->set('security.mode', 'enforce');
        config()->set('security.enabled', true);
        config()->set('security.alerts.webhook_url', '');
        config()->set('security.alerts.mail_to', '');
    }

    private function fromAttacker(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55']);
    }

    public function test_blocked_ip_is_rejected_before_routing(): void
    {
        SecurityIpRule::query()->create([
            'ip'         => '203.0.113.55',
            'type'       => 'block',
            'source'     => 'manual',
            'reason'     => 'Test block',
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->fromAttacker()->getJson('/__security_echo');

        $response->assertStatus(403)->assertJsonPath('code', 'SECURITY_BLOCKED');
    }

    public function test_hostile_payload_is_blocked_and_redacted_in_the_audit_log(): void
    {
        $response = $this->fromAttacker()->postJson('/__security_echo', [
            'q'        => "' UNION SELECT password FROM users --",
            'password' => 'super-secret-password',
        ]);

        $response->assertStatus(403);

        $event = SecurityEvent::query()->ofType('signature.detection')->first();
        $this->assertNotNull($event);
        $this->assertSame('block', $event->action);
        $this->assertContains('sqli', (array) $event->categories);
        $this->assertStringNotContainsString('super-secret-password', json_encode($event->context));
        $this->assertSame('[REDACTED]', $event->context['request']['json']['password'] ?? null);
    }

    public function test_benign_request_passes_and_receives_security_headers(): void
    {
        $response = $this->fromAttacker()->getJson('/__security_echo');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertDatabaseMissing('security_events', ['event_type' => 'signature.detection']);
    }

    public function test_rich_text_is_sanitised_before_reaching_the_controller(): void
    {
        // The XSS signature itself is scored, so run this in observe mode to
        // isolate the sanitisation behaviour from the blocking verdict.
        config()->set('security.mode', 'observe');
        $response = $this->fromAttacker()->postJson('/__security_echo', [
            'description' => '<p>Safe</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
        ]);

        $response->assertOk();
        $clean = $response->json('received.description');
        $this->assertStringContainsString('<p>Safe</p>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_observe_mode_logs_but_never_blocks(): void
    {
        config()->set('security.mode', 'observe');

        $this->fromAttacker()
            ->postJson('/__security_echo', ['q' => "' UNION SELECT password FROM users --"])
            ->assertOk();

        $this->assertDatabaseHas('security_events', ['event_type' => 'signature.detection']);
    }

    public function test_auto_block_escalates_repeat_offenders(): void
    {
        config()->set('security.auto_block.incident_threshold', 3);
        $service = $this->app->make(IpReputationService::class);

        $this->assertNull($service->noteIncident('203.0.113.66', 'test', 90));
        $this->assertNull($service->noteIncident('203.0.113.66', 'test', 90));
        $rule = $service->noteIncident('203.0.113.66', 'test', 90);

        $this->assertNotNull($rule);
        $this->assertSame('auto', $rule->source);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.66'])
            ->getJson('/__security_echo')
            ->assertStatus(403);
    }

    public function test_allowlisted_ip_cannot_be_autoblocked(): void
    {
        config()->set('security.ip_allowlist', ['203.0.113.77']);

        $this->assertNull(
            $this->app->make(IpReputationService::class)->noteIncident('203.0.113.77', 'test', 95)
        );
        $this->assertNull(
            $this->app->make(IpReputationService::class)->block('203.0.113.77', 'test')
        );
    }

    public function test_reset_grant_is_single_use_and_client_bound(): void
    {
        /** @var ResetGrantService $grants */
        $grants = $this->app->make(ResetGrantService::class);
        $request = Request::create('api/auth/forgot-password/reset-password', 'POST', [], [], [], [
            'REMOTE_ADDR'    => '203.0.113.88',
            'HTTP_USER_AGENT'=> 'PHPUnit',
        ]);
        $otherClient = Request::create('api/auth/forgot-password/reset-password', 'POST', [], [], [], [
            'REMOTE_ADDR'    => '203.0.113.99',
            'HTTP_USER_AGENT'=> 'PHPUnit',
        ]);

        $this->assertFalse($grants->consume('password_reset', 'victim@example.com', $request));

        $grants->issue('password_reset', 'victim@example.com', $request);

        $this->assertFalse($grants->consume('password_reset', 'attacker@example.com', $request));
        $this->assertFalse($grants->consume('password_reset', 'victim@example.com', $otherClient));
        $this->assertTrue($grants->consume('password_reset', 'victim@example.com', $request));
        $this->assertFalse($grants->consume('password_reset', 'victim@example.com', $request));
    }
}
