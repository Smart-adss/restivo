<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Subscription\InvoiceIssuer;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\Security\Totp;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Sprint17LaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_htaccess_and_fallback_block_env_and_artisan(): void
    {
        $public = file_get_contents(base_path('public/.htaccess'));
        $fallback = file_get_contents(base_path('deploy/public_html.htaccess'));

        $this->assertStringContainsString('.env', $public);
        $this->assertStringContainsString('artisan', $public);
        $this->assertStringContainsString('Require all denied', $public);
        $this->assertStringContainsString('.env', $fallback);
        $this->assertStringContainsString('artisan', $fallback);
    }

    public function test_mail_send_test_queues_a_message(): void
    {
        Mail::fake();

        $this->artisan('mail:send-test', ['email' => 'ops@example.com'])
            ->assertSuccessful();

        Mail::assertQueued(\App\Mail\TemplatedOutboundMail::class, function ($mail) {
            return $mail->hasTo('ops@example.com');
        });
    }

    public function test_day_report_query_uses_branch_and_placed_at_window(): void
    {
        $sql = '';
        DB::listen(function ($query) use (&$sql): void {
            if (str_contains($query->sql, 'from "orders"') || str_contains($query->sql, 'from `orders`')) {
                $sql = $query->sql;
            }
        });

        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        app(TenantContext::class)->set($created['tenant']->id);

        app(\App\Domain\Finance\Actions\BuildDailyBranchReport::class)
            ->handle($created['branch'], now('Africa/Cairo')->toDateString());

        $this->assertNotSame('', $sql);
        $this->assertStringContainsString('branch_id', $sql);
        $this->assertStringContainsString('placed_at', $sql);
        $this->assertStringNotContainsString('select * from', strtolower($sql));
    }

    public function test_public_menu_with_200_products_stays_under_two_seconds(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        app(TenantContext::class)->set($created['tenant']->id);
        $category = app(CreateCategory::class)->handle([
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
        ]);

        for ($i = 1; $i <= 200; $i++) {
            app(CreateProduct::class)->handle([
                'category_id' => $category->id,
                'name_ar' => 'منتج '.$i,
                'name_en' => 'Product '.$i,
                'base_price' => '10.00',
            ]);
        }

        // Warm caches / autoload outside the timed window.
        $this->get(route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]))->assertOk();

        $started = hrtime(true);
        $this->get(route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]))->assertOk()->assertSee('منتج 200', false);
        $elapsed = (hrtime(true) - $started) / 1e9;

        $this->assertLessThan(2.0, $elapsed, 'Public menu with 200 products exceeded 2s: '.$elapsed);
        $this->assertStringContainsString('0.012', file_get_contents(base_path('docs/RELEASE_NOTES.md')));
    }

    public function test_platform_totp_setup_and_challenge_gate_platform_routes(): void
    {
        config(['restivo.require_platform_totp' => true]);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('platform.tenants.index'))
            ->assertRedirect(route('platform.totp.setup'));

        $secret = Totp::generateSecret();
        $this->withSession(['totp_setup_secret' => $secret])
            ->actingAs($admin)
            ->post(route('platform.totp.setup.confirm'), [
                'code' => Totp::codeAt($secret, intdiv(time(), 30)),
            ])
            ->assertRedirect(route('platform.tenants.index'));

        $admin->refresh();
        $this->assertNotNull($admin->totp_confirmed_at);

        $this->actingAs($admin);
        session()->forget('platform_totp_ok');

        $this->get(route('platform.tenants.index'))
            ->assertRedirect(route('platform.totp.challenge'));

        $code = Totp::codeAt((string) $admin->totp_secret, intdiv(time(), 30));

        $this->post(route('platform.totp.verify'), ['code' => $code])
            ->assertRedirect(route('platform.tenants.index'));

        $this->get(route('platform.tenants.index'))->assertOk();
    }

    public function test_billing_webhook_rejects_invalid_signature_without_changing_invoice(): void
    {
        config(['services.billing.webhook_secret' => 'test-secret']);

        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        app(TenantContext::class)->set($created['tenant']->id);
        $subscription = $created['tenant']->subscription;
        $invoice = app(InvoiceIssuer::class)->issue(
            $subscription,
            $subscription->current_period_end->copy(),
            $subscription->current_period_end->copy()->addMonth(),
        );

        $body = json_encode([
            'invoice_public_id' => $invoice->public_id,
            'provider_ref' => 'PAY-BAD-1',
            'method' => 'gateway',
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('api.webhooks.billing'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Restivo-Signature' => 'deadbeef',
            ],
            $body
        )->assertForbidden();

        $this->assertSame('open', $invoice->fresh()->status);
        $this->assertSame(0, DB::table('subscription_payments')->where('provider_ref', 'PAY-BAD-1')->count());

        $good = hash_hmac('sha256', $body, 'test-secret');
        $this->call(
            'POST',
            route('api.webhooks.billing'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Restivo-Signature' => $good,
            ],
            $body
        )->assertOk();

        $this->assertSame('paid', SubscriptionInvoice::withoutGlobalScope('tenant')->find($invoice->id)->status);
    }

    public function test_hosting_and_ops_docs_exist(): void
    {
        foreach ([
            'docs/17-hosting-provider-answers.md',
            'docs/17-ops-runbook.md',
            'docs/17-query-index-review.md',
            'docs/17-backup-restore-drill.md',
            'docs/17-billing-collection.md',
            'docs/RELEASE_NOTES.md',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }

        $this->assertStringContainsString('mail:send-test', file_get_contents(base_path('docs/17-ops-runbook.md')));
        $this->assertStringContainsString('queue:work', file_get_contents(base_path('docs/17-ops-runbook.md')));
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $slug): array
    {
        return [
            'legal_name' => 'شركة '.$slug,
            'display_name_ar' => 'مطعم '.$slug,
            'display_name_en' => 'Restaurant '.$slug,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'phone' => '01000000000',
            'owner_name' => 'مالك '.$slug,
            'owner_email' => 'owner-'.$slug.'@example.com',
            'branch_name_ar' => 'الفرع الرئيسي',
            'branch_name_en' => 'Main branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع التحرير',
        ];
    }
}
