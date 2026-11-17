<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Pos\Actions\CloseShift;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Reservations\ReservationStatus;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\CashRegister;
use App\Models\Feature;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Sprint18PilotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00', 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pilot_acceptance_sale_public_order_reservation_and_shift_close(): void
    {
        [$owner, $tenant, $branch, $register, $product] = $this->seedPilot('pilot');

        $this->actingAs($owner);
        $shift = app(OpenShift::class)->handle($register->id, '100.00');

        $sale = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->assertSame('completed', $sale['order']->status);

        $public = $this->postJson(route('api.public.orders.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'pickup',
            'customer_name' => 'أحمد',
            'customer_phone' => '+201012345678',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        $this->assertSame('114.00', $public['total']);
        $this->assertSame(2, Order::query()->count());

        $reservation = $this->postJson(route('api.public.reservations.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'customer_name' => 'سارة',
            'customer_phone' => '+201011111111',
            'guest_count' => 2,
            'reserved_at' => '2026-11-17 14:00:00',
        ])->assertCreated()->json('data');

        $this->assertSame(ReservationStatus::PENDING, $reservation['status']);
        $this->assertSame(1, Reservation::query()->count());

        $closed = app(CloseShift::class)->handle($shift->fresh(), '214.00');
        $this->assertSame('214.00', (string) $closed->expected_cash);
        $this->assertSame('0.00', (string) $closed->difference);
        $this->assertSame('closed', $closed->status);
    }

    public function test_ops_health_check_passes_when_failed_jobs_empty(): void
    {
        $this->artisan('ops:health-check', ['--disk-min-mb' => 1])
            ->assertSuccessful()
            ->expectsOutputToContain('Health check OK');
    }

    public function test_ops_health_check_fails_when_failed_jobs_exist(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Simulated failure for pilot monitoring',
            'failed_at' => now(),
        ]);

        $this->artisan('ops:health-check', ['--disk-min-mb' => 1])
            ->assertFailed();
    }

    public function test_platform_failed_jobs_screen_lists_rows(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => "RuntimeException: boom\nstack",
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('platform.failed-jobs.index'))
            ->assertOk()
            ->assertSee('RuntimeException: boom', false);
    }

    public function test_sprint_18_docs_and_rollback_archive_path_exist(): void
    {
        foreach ([
            'docs/18-pilot-runbook.md',
            'docs/18-daily-monitoring.md',
            'docs/18-owner-training.md',
            'docs/18-rollback-plan.md',
        ] as $path) {
            $this->assertFileExists(base_path($path));
        }

        $rollback = file_get_contents(base_path('docs/18-rollback-plan.md'));
        $this->assertStringContainsString('/home/{account}/releases/', $rollback);
        $this->assertStringContainsString('public_html', $rollback);
        $this->assertStringContainsString('ops:health-check', file_get_contents(base_path('docs/18-daily-monitoring.md')));
    }

    /**
     * @return array{0: User, 1: \App\Models\Tenant, 2: \App\Models\Branch, 3: CashRegister, 4: \App\Models\Product}
     */
    private function seedPilot(string $slug): array
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة '.$slug,
            'display_name_ar' => 'مطعم '.$slug,
            'display_name_en' => 'Restaurant '.$slug,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'phone' => '+201000000000',
            'owner_name' => 'مالك '.$slug,
            'owner_email' => 'owner-'.$slug.'@example.com',
            'branch_name_ar' => 'الفرع الرئيسي',
            'branch_name_en' => 'Main branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع التحرير',
            'plan_code' => 'growth',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);

        foreach (['module.pos', 'module.online_ordering', 'module.reservations'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
        }
        app(FeatureGate::class)->flush();

        DB::table('operating_hours')
            ->where('branch_id', $created['branch']->id)
            ->where('day_of_week', Carbon::now('Africa/Cairo')->dayOfWeekIso - 1)
            ->update([
                'opens_at' => '09:00:00',
                'closes_at' => '22:00:00',
                'is_closed' => false,
            ]);

        $tax = TaxRate::query()->create([
            'name' => 'VAT',
            'rate_percent' => '14.000',
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);
        $category = app(CreateCategory::class)->handle([
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
        ]);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'tax_rate_id' => $tax->id,
            'name_ar' => 'شاي',
            'name_en' => 'Tea',
            'base_price' => '100.00',
        ]);
        $register = CashRegister::query()->create([
            'branch_id' => $created['branch']->id,
            'name' => 'صندوق 1',
            'is_active' => true,
        ]);
        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();

        return [$owner, $created['tenant']->fresh(), $created['branch'], $register, $product];
    }
}
