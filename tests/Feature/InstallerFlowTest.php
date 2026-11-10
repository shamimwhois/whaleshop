<?php

namespace Tests\Feature;

use App\Services\InstallerPermissionCheckerService;
use App\Services\InstallerRequirementsCheckerService;
use App\Services\InstallerService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstallerFlowTest extends TestCase
{
    public function test_php_version_check_reports_current_version_and_support(): void
    {
        $result = app(InstallerRequirementsCheckerService::class)->checkPhpVersion();

        $this->assertSame(PHP_VERSION, $result['full']);
        $this->assertStringStartsWith(PHP_MAJOR_VERSION . '.', $result['current']);
        $this->assertTrue($result['supported'], 'The PHP version running the suite must satisfy the installer minimum.');
    }

    public function test_php_version_check_can_fail_for_an_impossible_minimum(): void
    {
        $result = app(InstallerRequirementsCheckerService::class)->checkPhpVersion('999.0.0');

        $this->assertFalse($result['supported']);
    }

    public function test_requirements_check_flags_missing_extensions(): void
    {
        $results = app(InstallerRequirementsCheckerService::class)->check([
            'php' => ['json', 'definitely_not_a_real_extension'],
        ]);

        $this->assertTrue($results['requirements']['php']['json']);
        $this->assertFalse($results['requirements']['php']['definitely_not_a_real_extension']);
        $this->assertTrue($results['errors']);
    }

    public function test_requirements_check_passes_when_all_extensions_are_loaded(): void
    {
        $results = app(InstallerRequirementsCheckerService::class)->check([
            'php' => ['json', 'pdo'],
        ]);

        $this->assertTrue($results['requirements']['php']['json']);
        $this->assertTrue($results['requirements']['php']['pdo']);
        $this->assertArrayNotHasKey('errors', $results);
    }

    public function test_permission_check_reports_each_checked_folder(): void
    {
        $results = app(InstallerPermissionCheckerService::class)->check([
            'storage'         => '755',
            'bootstrap/cache' => '755',
        ]);

        $this->assertCount(2, $results['permissions']);

        foreach ($results['permissions'] as $permission) {
            $this->assertArrayHasKey('folder', $permission);
            $this->assertArrayHasKey('permission', $permission);
            $this->assertArrayHasKey('isSet', $permission);
            $this->assertIsBool($permission['isSet']);
        }

        $folders = array_column($results['permissions'], 'folder');
        $this->assertSame(['storage', 'bootstrap/cache'], $folders);
    }

    public function test_license_check_accepts_a_valid_license_response(): void
    {
        Http::fake([
            '*' => Http::response(['status' => true, 'message' => 'ok'], 200, ['Content-Type' => 'application/json']),
        ]);

        $response = app(InstallerService::class)->licenseCodeChecker(['license_key' => 'TEST-LICENSE']);

        $this->assertTrue($response->status);
        $this->assertSame('ok', $response->message);

        Http::assertSent(function ($request) {
            return $request['license_code'] === 'TEST-LICENSE'
                && $request['purpose'] === 'install';
        });
    }

    public function test_license_check_reports_rejection_from_the_license_server(): void
    {
        Http::fake([
            '*' => Http::response(['status' => false, 'message' => 'Invalid license'], 200, ['Content-Type' => 'application/json']),
        ]);

        $response = app(InstallerService::class)->licenseCodeChecker(['license_key' => 'BAD-LICENSE']);

        $this->assertFalse($response->status);
        $this->assertSame('Invalid license', $response->message);
    }

    public function test_license_check_returns_failure_when_the_server_is_unreachable(): void
    {
        Http::fake([
            '*' => Http::response('server error', 500),
        ]);

        $response = app(InstallerService::class)->licenseCodeChecker(['license_key' => 'TEST-LICENSE']);

        $this->assertFalse($response->status);
    }
}
