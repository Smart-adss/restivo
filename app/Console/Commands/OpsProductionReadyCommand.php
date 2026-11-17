<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OpsProductionReadyCommand extends Command
{
    protected $signature = 'ops:production-ready
                            {--strict : Treat warnings as failures}
                            {--json : Machine-readable output}';

    protected $description = 'Verify production configuration before opening paying tenants';

    public function handle(): int
    {
        $checks = [];

        $checks[] = $this->check(
            'app_env_production',
            config('app.env') === 'production',
            'hard',
            'APP_ENV must be production',
            'APP_ENV='.(string) config('app.env'),
        );

        $checks[] = $this->check(
            'app_debug_off',
            config('app.debug') === false,
            'hard',
            'APP_DEBUG must be false',
            'APP_DEBUG='.(config('app.debug') ? 'true' : 'false'),
        );

        $checks[] = $this->check(
            'app_key_set',
            filled(config('app.key')),
            'hard',
            'APP_KEY must be set',
            filled(config('app.key')) ? 'set' : 'empty',
        );

        $checks[] = $this->check(
            'app_url_https',
            str_starts_with((string) config('app.url'), 'https://'),
            'hard',
            'APP_URL must use https://',
            (string) config('app.url'),
        );

        $checks[] = $this->check(
            'session_secure_cookie',
            (bool) config('session.secure'),
            'hard',
            'SESSION_SECURE_COOKIE must be true behind HTTPS',
            config('session.secure') ? 'true' : 'false',
        );

        $checks[] = $this->check(
            'platform_totp_required',
            (bool) config('restivo.require_platform_totp'),
            'hard',
            'REQUIRE_PLATFORM_TOTP must be true',
            config('restivo.require_platform_totp') ? 'true' : 'false',
        );

        $checks[] = $this->check(
            'queue_database',
            config('queue.default') === 'database',
            'hard',
            'QUEUE_CONNECTION must be database',
            (string) config('queue.default'),
        );

        $checks[] = $this->check(
            'cache_not_redis',
            config('cache.default') !== 'redis',
            'hard',
            'CACHE_STORE must not be redis on shared hosting',
            (string) config('cache.default'),
        );

        $mailer = (string) config('mail.default');
        $checks[] = $this->check(
            'mailer_not_dev',
            ! in_array($mailer, ['array', 'log'], true),
            'soft',
            'MAIL_MAILER should be SMTP (or equivalent) on production',
            $mailer,
        );

        $webhook = (string) config('services.billing.webhook_secret', '');
        $checks[] = $this->check(
            'billing_manual_ok',
            true,
            'info',
            $webhook === ''
                ? 'BILLING_WEBHOOK_SECRET empty — manual collection only (accepted for launch)'
                : 'BILLING_WEBHOOK_SECRET set — webhook path enabled',
            $webhook === '' ? 'empty' : 'set',
        );

        $htaccess = File::get(base_path('public/.htaccess'));
        $checks[] = $this->check(
            'htaccess_blocks_env',
            str_contains($htaccess, '.env') && str_contains($htaccess, 'artisan'),
            'hard',
            'public/.htaccess must block .env and artisan',
            str_contains($htaccess, '.env') ? 'rules present' : 'missing',
        );

        $platformWithTotp = User::query()
            ->where('is_platform_user', true)
            ->whereNotNull('totp_confirmed_at')
            ->count();

        $checks[] = $this->check(
            'platform_totp_enrolled',
            $platformWithTotp > 0,
            'soft',
            'At least one platform admin must complete TOTP enrollment',
            (string) $platformWithTotp,
        );

        $hardFails = collect($checks)->where('severity', 'hard')->where('ok', false)->count();
        $softFails = collect($checks)->where('severity', 'soft')->where('ok', false)->count();
        $failed = $hardFails > 0 || ($this->option('strict') && $softFails > 0);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $failed,
                'hard_fails' => $hardFails,
                'soft_fails' => $softFails,
                'checks' => $checks,
                'checked_at' => now('Africa/Cairo')->toDateTimeString().' Africa/Cairo',
            ], JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['check', 'severity', 'ok', 'detail'],
                collect($checks)->map(fn (array $c) => [
                    $c['id'],
                    $c['severity'],
                    $c['ok'] ? 'yes' : 'no',
                    $c['detail'],
                ])->all()
            );

            foreach ($checks as $check) {
                if (! $check['ok'] && $check['severity'] !== 'info') {
                    $this->error($check['message'].' ('.$check['detail'].')');
                }
            }

            if (! $failed) {
                $this->info('Production readiness OK'.($softFails > 0 ? ' (with soft warnings)' : ''));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{id: string, ok: bool, severity: string, message: string, detail: string}
     */
    private function check(string $id, bool $ok, string $severity, string $message, string $detail): array
    {
        return compact('id', 'ok', 'severity', 'message', 'detail');
    }
}
