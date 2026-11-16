<?php

namespace Tests\Feature;

use App\Domain\Subscription\InvoiceIssuer;
use App\Domain\Subscription\RecordPayment;
use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\AuditLog;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\Features\DuplicatePayment;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\LimitExceeded;
use App\Support\Features\Usage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_plan_catalog_keys_match_and_renaming_a_plan_keeps_its_code(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(FeatureCatalog::all()),
            Feature::query()->pluck('key')->all(),
        );

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->actingAs($admin)->put(route('platform.plans.update', 'starter'), [
            'name_ar' => 'بداية معدلة',
            'name_en' => 'Starter renamed',
            'description' => 'وصف',
            'is_active' => 1,
            'is_public' => 1,
            'trial_days' => 0,
            'monthly_amount' => 499,
            'yearly_amount' => 4990,
        ])->assertRedirect();

        $plan = Plan::query()->where('code', 'starter')->first();

        $this->assertSame('starter', $plan->code);
        $this->assertSame('بداية معدلة', $plan->name_ar);
        $this->assertTrue(Feature::query()->where('key', 'module.pos')->exists());
    }

    public function test_a_repeated_payment_does_not_extend_the_period_twice(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        app(TenantContext::class)->set($created['tenant']->id);
        $subscription = $created['tenant']->subscription;
        $start = $subscription->current_period_end->copy();
        $end = $start->copy()->addMonth();
        $invoice = app(InvoiceIssuer::class)->issue($subscription, $start, $end);

        app(RecordPayment::class)->handle($invoice, 'manual', 'BANK-1');
        $extended = $subscription->fresh()->current_period_end->toDateTimeString();

        $this->assertTrue($subscription->fresh()->current_period_end->greaterThan($subscription->current_period_start));

        try {
            app(RecordPayment::class)->handle($invoice->fresh(), 'manual', 'BANK-1');
            $this->fail('A duplicate payment was accepted.');
        } catch (DuplicatePayment) {
            $this->assertSame($extended, $subscription->fresh()->current_period_end->toDateTimeString());
            $this->assertSame(1, DB::table('subscription_payments')->where('provider_ref', 'BANK-1')->count());
        }
    }

    public function test_usage_rejects_the_call_that_would_pass_the_limit(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);
        $featureId = Feature::query()->where('key', 'orders.monthly')->value('id');
        DB::table('plan_features')->where('feature_id', $featureId)->where('plan_id', $created['tenant']->subscription->plan_id)->update(['value' => '1']);

        app(Usage::class)->assertCanConsume('orders.monthly');
        $this->assertSame(1, app(Usage::class)->used('orders.monthly'));

        try {
            app(Usage::class)->assertCanConsume('orders.monthly');
            $this->fail('The counter passed the limit.');
        } catch (LimitExceeded) {
            $this->assertSame(1, app(Usage::class)->used('orders.monthly'));
        }
    }

    public function test_expired_subscription_blocks_creation_and_keeps_the_owner_view(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        $created['tenant']->subscription->update(['status' => 'expired']);

        $this->actingAs($created['owner'])->get(route('app.branches.index'))->assertOk();
        $this->actingAs($created['owner'])->get(route('app.subscription'))->assertOk()->assertSee('1 / 1');
        $this->actingAs($created['owner'])->post(route('app.branches.store'), [
            'name_ar' => 'فرع',
            'name_en' => 'Branch',
            'code' => 'X',
            'city' => 'القاهرة',
            'address' => 'شارع',
        ])->assertForbidden();
    }

    public function test_closing_pos_hides_the_screen_and_blocks_the_action(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        $featureId = Feature::query()->where('key', 'module.pos')->value('id');

        $this->actingAs($created['owner'])->get(route('app.home'))->assertOk()->assertSee('نقطة البيع');
        $this->actingAs($created['owner'])->post(route('app.pos.store'))->assertRedirect(route('app.pos'));

        DB::table('plan_features')->where('feature_id', $featureId)->where('plan_id', $created['tenant']->subscription->plan_id)->update(['value' => '0']);

        $this->actingAs($created['owner'])->get(route('app.home'))->assertOk()->assertDontSee('نقطة البيع');
        $this->actingAs($created['owner'])->get(route('app.pos'))->assertForbidden();
        $this->actingAs($created['owner'])->post(route('app.pos.store'))->assertForbidden();
    }

    public function test_downgrade_is_rejected_when_active_branches_do_not_fit(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'pro']);
        app(TenantContext::class)->set($created['tenant']->id);
        app(CreateBranch::class)->handle([
            'name_ar' => 'فرع ثان',
            'name_en' => 'Second',
            'code' => 'B2',
            'city' => 'الجيزة',
            'address' => 'الهرم',
        ]);

        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->actingAs($admin)->post(route('platform.tenants.plan.update', $created['tenant']->public_id), [
            'plan_code' => 'starter',
        ])->assertSessionHasErrors('plan_code');

        $this->assertSame('pro', $created['tenant']->subscription->fresh()->plan->code);
    }

    public function test_suspend_and_staff_audit_do_not_store_passwords(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        $admin = User::query()->where('email', 'admin@example.com')->first();

        $this->actingAs($created['owner'])->post(route('app.staff.store'), [
            'name' => 'كاشير',
            'email' => 'cashier-bill@example.com',
            'password' => 'Password123',
            'role_key' => 'cashier',
            'branch_ids' => [$created['branch']->id],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('platform.tenants.suspend', $created['tenant']->public_id))->assertRedirect();

        $encoded = AuditLog::query()->get()->map(fn (AuditLog $log): string => json_encode($log->only(['before', 'after'])))->implode(' ');

        $this->assertStringNotContainsString('Password123', $encoded);
        $this->assertStringNotContainsString('ChangeMe123', $encoded);
        $this->assertTrue(AuditLog::query()->where('action', 'tenant.suspended')->exists());
        $this->actingAs($admin)->get(route('platform.audit.index'))->assertOk()->assertSee('tenant.suspended')->assertDontSee('Password123');
    }

    public function test_support_cannot_record_payments_or_see_a_phone_number(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        $support = User::factory()->create([
            'email' => 'support@example.com',
            'is_platform_user' => true,
            'password' => 'Password123',
        ]);
        $role = Role::withoutGlobalScope('tenant')->whereNull('tenant_id')->where('key', 'platform_support')->first();
        DB::table('user_roles')->insert([
            'user_id' => $support->id,
            'role_id' => $role->id,
            'branch_id' => null,
        ]);

        $this->actingAs($created['owner'])->post(route('app.support.store'), [
            'subject' => 'مساعدة',
            'body' => 'نحتاج توضيحاً',
        ])->assertRedirect();

        $ticket = $created['tenant']->id;
        $publicId = DB::table('support_tickets')->where('tenant_id', $ticket)->value('public_id');

        $this->actingAs($support)
            ->get(route('platform.support.show', $publicId))
            ->assertOk()
            ->assertSee('نحتاج توضيحاً')
            ->assertDontSee('01000000000');

        $this->actingAs($support)->post(route('platform.support.reply', $publicId), [
            'body' => 'تم الاستلام',
        ])->assertRedirect();

        $this->assertSame(2, DB::table('support_messages')->count());
        $this->actingAs($support)->post(route('platform.tenants.payments.store', $created['tenant']->public_id), [
            'invoice_id' => DB::table('subscription_invoices')->value('id'),
            'provider_ref' => 'NOPE',
        ])->assertForbidden();
        $this->assertFalse($support->hasPermission('platform.billing.record_payment'));
    }

    public function test_invoice_command_is_idempotent_and_state_command_can_run_twice(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        $subscription = $created['tenant']->subscription;
        $subscription->update([
            'status' => 'active',
            'current_period_end' => now()->addDays(2),
        ]);

        $before = SubscriptionInvoice::withoutGlobalScope('tenant')->where('subscription_id', $subscription->id)->count();
        $this->artisan('billing:issue-invoices')->assertSuccessful();
        $after = SubscriptionInvoice::withoutGlobalScope('tenant')->where('subscription_id', $subscription->id)->count();
        $audits = AuditLog::query()->where('action', 'billing.invoice_issued')->count();

        $this->assertSame($before + 1, $after);
        $this->artisan('billing:issue-invoices')->assertSuccessful();
        $this->assertSame($after, SubscriptionInvoice::withoutGlobalScope('tenant')->where('subscription_id', $subscription->id)->count());
        $this->assertSame($audits, AuditLog::query()->where('action', 'billing.invoice_issued')->count());

        $subscription->update([
            'status' => 'active',
            'current_period_end' => now()->subHours(2),
        ]);

        $this->artisan('billing:advance-states')->assertSuccessful();
        $this->artisan('billing:advance-states')->assertSuccessful();

        $this->assertSame('past_due', $subscription->fresh()->status);
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
