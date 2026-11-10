<?php

namespace Tests\Unit;

use App\Security\Support\ClientIpResolver;
use App\Security\Support\HtmlSanitizer;
use App\Security\Support\SensitiveDataRedactor;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecuritySupportTest extends TestCase
{
    public function test_redactor_masks_credentials_and_tokens(): void
    {
        $redactor = new SensitiveDataRedactor();

        $clean = $redactor->clean([
            'password'             => 'hunter2',
            'password_confirmation'=> 'hunter2',
            'nested'               => ['x-api-key' => 'SECRET-KEY-123', 'safe' => 'visible'],
            'note'                 => 'Bearer abcdefghijklmnop1234567890',
        ]);

        $this->assertSame('[REDACTED]', $clean['password']);
        $this->assertSame('[REDACTED]', $clean['password_confirmation']);
        $this->assertSame('[REDACTED]', $clean['nested']['x-api-key']);
        $this->assertSame('visible', $clean['nested']['safe']);
        $this->assertStringNotContainsString('abcdefghijklmnop', $clean['note']);
        $this->assertStringContainsString('Bearer', $clean['note']);
    }

    public function test_html_sanitizer_strips_scripts_handlers_and_unsafe_urls(): void
    {
        $sanitizer = new HtmlSanitizer();

        $dirty = '<script>alert(1)</script>'
            .'<p onclick="steal()">Hello <b>world</b></p>'
            .'<a href="javascript:alert(1)">bad link</a> '
            .'<a href="https://example.com" target="_blank">good link</a>'
            .'<iframe src="https://evil.example"></iframe>'
            .'<img src="data:image/png;base64,iVBORw0KGgo=" onerror="alert(1)" alt="pic">';

        $clean = (string) $sanitizer->clean($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('<b>world</b>', $clean);
        $this->assertStringContainsString('https://example.com', $clean);
        $this->assertStringContainsString('noopener noreferrer', $clean);
    }

    public function test_html_sanitizer_leaves_plain_text_untouched(): void
    {
        $sanitizer = new HtmlSanitizer();
        $plain = 'A simple sentence with no markup at all.';

        $this->assertSame($plain, $sanitizer->clean($plain));
    }

    public function test_forwarded_for_is_ignored_without_a_trusted_proxy(): void
    {
        config()->set('security.trusted_proxies', []);

        $request = Request::create('api/test', 'GET', [], [], [], [
            'REMOTE_ADDR'          => '198.51.100.4',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 5.6.7.8',
        ]);

        $this->assertSame('198.51.100.4', (new ClientIpResolver())->resolve($request));
    }

    public function test_forwarded_for_is_honoured_only_from_a_trusted_proxy(): void
    {
        config()->set('security.trusted_proxies', ['10.0.0.0/8']);

        $request = Request::create('api/test', 'GET', [], [], [], [
            'REMOTE_ADDR'          => '10.0.0.9',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.20, 10.0.0.9',
        ]);

        $this->assertSame('203.0.113.20', (new ClientIpResolver())->resolve($request));
    }

    public function test_ipv6_and_ipv4_with_port_are_normalised(): void
    {
        $resolver = new ClientIpResolver();

        $v6 = Request::create('api/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '[2001:db8::1]:443',
        ]);
        $v4 = Request::create('api/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.7:443',
        ]);

        $this->assertSame('2001:db8::1', $resolver->resolve($v6));
        $this->assertSame('203.0.113.7', $resolver->resolve($v4));
    }

    public function test_allowlist_prevents_blocking_localhost(): void
    {
        config()->set('security.ip_allowlist', ['127.0.0.1', '::1', '10.0.0.0/8']);

        $resolver = new ClientIpResolver();
        $this->assertTrue($resolver->isAllowlisted('127.0.0.1'));
        $this->assertTrue($resolver->isAllowlisted('10.1.2.3'));
        $this->assertFalse($resolver->isAllowlisted('203.0.113.99'));
    }
}
