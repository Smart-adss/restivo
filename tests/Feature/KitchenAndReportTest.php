<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\BuildDailyBranchReport;
use App\Domain\Finance\Actions\FillDailyBranchSummaries;
use App\Domain\Kitchen\Actions\CreatePrepStation;
use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Ordering\Actions\TransitionOrderStatus;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\CashRegister;
use App\Models\DailyBranchSummary;
use App\Models\Feature;
use App\Models\KitchenTicket;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KitchenAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_product_without_station_does_not_create_ticket(): void
    {
        [$owner, $branch, $withStation, $withoutStation, $register] = $this->seedKitchen('alpha');
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');

        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withoutStation->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->assertSame(0, KitchenTicket::query()->count());

        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withStation->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->assertSame(1, KitchenTicket::query()->count());
        $this->assertSame('queued', KitchenTicket::query()->first()->status);
    }

    public function test_guest_accept_creates_ticket_and_poll_omits_phones(): void
    {
        [$owner, $branch, $withStation] = $this->seedKitchen('alpha', withPos: false);
        $this->actingAs($owner);

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'أحمد',
            'customer_phone' => '+201012345678',
            'items' => [['product_id' => $withStation->id, 'quantity' => 1]],
            'status' => 'placed',
        ]);

        $this->assertSame(0, KitchenTicket::query()->count());

        app(TransitionOrderStatus::class)->handle($order, 'accepted');

        $this->assertSame(1, KitchenTicket::query()->count());

        $response = $this->getJson(route('app.kitchen.poll', [
            'branch_id' => $branch->id,
        ]));

        $response->assertOk();
        $json = $response->json('data.tickets');
        $this->assertNotEmpty($json);
        $encoded = json_encode($json);
        $this->assertStringNotContainsString('+201012345678', $encoded);
        $this->assertStringNotContainsString('114.00', $encoded);
        $this->assertStringNotContainsString('price', strtolower($encoded));
    }

    public function test_late_ticket_is_labelled_with_text(): void
    {
        [$owner, $branch, $withStation, $withoutStation, $register] = $this->seedKitchen('alpha');
        $this->actingAs($owner);

        DB::table('branch_settings')->updateOrInsert(
            ['branch_id' => $branch->id, 'key' => 'kitchen.late_minutes'],
            ['tenant_id' => $branch->tenant_id, 'value' => json_encode(1)],
        );

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withStation->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $ticket = KitchenTicket::query()->first();
        $ticket->forceFill(['created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5)])->save();

        app()->setLocale('ar');
        $response = $this->getJson(route('app.kitchen.poll', ['branch_id' => $branch->id]));
        $response->assertOk()
            ->assertJsonPath('data.tickets.0.is_late', true)
            ->assertJsonPath('data.tickets.0.late_label', 'متأخر');
    }

    public function test_day_report_matches_known_sale_totals(): void
    {
        [$owner, $branch, $withStation, $withoutStation, $register] = $this->seedKitchen('alpha');
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withStation->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withoutStation->id, 'quantity' => 1]],
            'payments' => [['method' => 'card', 'amount' => '114.00']],
        ]);

        $date = now('Africa/Cairo')->toDateString();
        $report = app(BuildDailyBranchReport::class)->handle($branch, $date);

        $this->assertSame(2, $report['orders_count']);
        $this->assertSame('200.00', $report['gross_total']);
        $this->assertSame('28.00', $report['tax_total']);
        $this->assertSame('200.00', $report['net_total']);
        $this->assertSame('114.00', $report['cash_total']);
        $this->assertSame('114.00', $report['electronic_total']);
        $this->assertSame('0.00', $report['refund_total']);
    }

    public function test_daily_summary_command_is_idempotent(): void
    {
        [$owner, $branch, $withStation, $withoutStation, $register] = $this->seedKitchen('alpha');
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withStation->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        app(FillDailyBranchSummaries::class)->handle();
        app(FillDailyBranchSummaries::class)->handle();

        $date = now('Africa/Cairo')->toDateString();
        $rows = DailyBranchSummary::query()
            ->where('branch_id', $branch->id)
            ->whereDate('business_date', $date)
            ->get();

        $this->assertSame(1, $rows->count());
        $this->assertSame(1, (int) $rows->first()->orders_count);
        $this->assertSame('100.00', (string) $rows->first()->gross_total);
    }

    public function test_commercial_launch_checklist_document_exists(): void
    {
        $path = base_path('docs/16-commercial-launch-checklist.md');
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('تقرير يوم الفرع', $contents);
        $this->assertStringContainsString('reports:fill-daily-summaries', $contents);
        $this->assertStringContainsString('تذكرة مطبخ', $contents);
    }

    /**
     * @return array{0: User, 1: \App\Models\Branch, 2: \App\Models\Product, 3?: \App\Models\Product, 4?: CashRegister}
     */
    private function seedKitchen(string $slug, bool $withPos = true): array
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
        app(FeatureGate::class)->flush();

        foreach (['module.kitchen', 'module.pos', 'module.online_ordering'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
        }
        app(FeatureGate::class)->flush();

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();
        $this->actingAs($owner);

        $station = app(CreatePrepStation::class)->handle([
            'branch_id' => $created['branch']->id,
            'name_ar' => 'المطبخ',
            'name_en' => 'Kitchen',
            'kind' => 'kitchen',
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
        $withStation = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'tax_rate_id' => $tax->id,
            'prep_station_id' => $station->id,
            'name_ar' => 'شاي محطة',
            'name_en' => 'Station tea',
            'base_price' => '100.00',
        ]);
        $withoutStation = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'tax_rate_id' => $tax->id,
            'name_ar' => 'شاي بلا محطة',
            'name_en' => 'No station tea',
            'base_price' => '100.00',
        ]);

        if (! $withPos) {
            return [$owner, $created['branch'], $withStation];
        }

        $register = CashRegister::query()->create([
            'branch_id' => $created['branch']->id,
            'name' => 'صندوق 1',
            'is_active' => true,
        ]);

        return [$owner, $created['branch'], $withStation, $withoutStation, $register];
    }
}
