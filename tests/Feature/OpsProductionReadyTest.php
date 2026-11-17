<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsProductionReadyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_production_ready_fails_when_debug_is_on(): void
    {
        $this->configureProductionBaseline();
        config(['app.debug' => true]);

        $this->artisan('ops:production-ready')
            ->assertFailed();
    }

    public function test_production_ready_passes_hard_checks_with_totp_warning(): void
    {
        $this->configureProductionBaseline();

        $this->artisan('ops:production-ready')
            ->assertSuccessful()
            ->expectsOutputToContain('Production readiness OK');

        $this->artisan('ops:production-ready', ['--strict' => true])
            ->assertFailed();
    }

    public function test_production_ready_strict_passes_after_platform_totp_enrollment(): void
    {
        $this->configureProductionBaseline();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $admin->forceFill([
            'totp_secret' => Totp::generateSecret(),
            'totp_confirmed_at' => now(),
        ])->save();

        $this->artisan('ops:production-ready', ['--strict' => true])
            ->assertSuccessful();
    }

    public function test_golive_docs_exist(): void
    {
        $this->assertFileExists(base_path('docs/19-production-golive.md'));
        $contents = file_get_contents(base_path('docs/19-production-golive.md'));
        $this->assertStringContainsString('ops:production-ready', $contents);
        $this->assertStringContainsString('HTTPS', $contents);
    }

    private function configureProductionBaseline(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://restivo.example',
            'session.secure' => true,
            'restivo.require_platform_totp' => true,
            'queue.default' => 'database',
            'cache.default' => 'file',
            'mail.default' => 'smtp',
            'services.billing.webhook_secret' => '',
        ]);
    }
}
