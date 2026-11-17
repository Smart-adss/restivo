<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateOption;
use App\Domain\Menu\Actions\CreateOptionGroup;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Ordering\Support\InvalidCoupon;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Feature;
use App\Models\Order;
use App\Models\TaxRate;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use App\Support\Features\Usage;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_create_order_snapshots_names_prices_and_tax_from_the_database(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(1, $order->number);
        $this->assertSame('placed', $order->status);
        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('14.00', $order->tax_total);
        $this->assertSame('114.00', $order->total);
        $this->assertSame('شاي', $order->items->first()->name_ar);
        $this->assertSame('Tea', $order->items->first()->name_en);
        $this->assertSame('100.00', $order->items->first()->unit_price);
        $this->assertSame('14.000', $order->items->first()->tax_rate_percent);

        $product->update(['base_price' => '200.00', 'name_en' => 'Changed']);
        $this->assertSame('100.00', $order->fresh()->items->first()->unit_price);
        $this->assertSame('Tea', $order->fresh()->items->first()->name_en);
    }

    public function test_invoice_numbers_are_sequential_per_branch_without_reuse(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $first = app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id));
        $second = app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id));

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
        $this->assertSame(2, Order::query()->count());
        $this->assertDatabaseHas('branch_order_sequences', [
            'branch_id' => $branch->id,
            'last_number' => 2,
        ]);
    }

    public function test_same_idempotency_key_returns_the_original_order_without_consuming_usage_again(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');
        $key = (string) Str::ulid();

        $first = app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id, $key));
        $second = app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id, $key));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, app(Usage::class)->used('orders.monthly'));
    }

    public function test_orders_monthly_limit_blocks_the_write(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');
        $featureId = Feature::query()->where('key', 'orders.monthly')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $tenant->subscription->plan_id)
            ->update(['value' => '1']);
        app(FeatureGate::class)->flush();

        app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id));

        try {
            app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id));
            $this->fail('An order above the monthly limit was written.');
        } catch (LimitExceeded) {
            $this->assertSame(1, Order::query()->count());
            $this->assertSame(1, app(Usage::class)->used('orders.monthly'));
        }
    }

    public function test_expired_coupon_and_other_branch_coupon_are_rejected(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $expired = Coupon::query()->create([
            'code' => 'OLD10',
            'kind' => 'percent',
            'value' => '10.00',
            'is_active' => true,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);

        try {
            app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id) + [
                'coupon_code' => $expired->code,
            ]);
            $this->fail('An expired coupon was accepted.');
        } catch (InvalidCoupon) {
            $this->assertSame(0, Order::query()->count());
        }

        foreach (['branches.max' => '2', 'module.multi_branch' => '1'] as $key => $value) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $tenant->subscription->plan_id)
                ->update(['value' => $value]);
        }
        app(FeatureGate::class)->flush();

        $otherBranch = app(\App\Domain\Tenancy\Actions\CreateBranch::class)->handle([
            'name_ar' => 'فرع آخر',
            'name_en' => 'Other',
            'code' => 'OTHER',
            'city' => 'الجيزة',
            'address' => 'شارع الهرم',
        ]);

        $restricted = Coupon::query()->create([
            'code' => 'BRANCHONLY',
            'kind' => 'fixed',
            'value' => '5.00',
            'is_active' => true,
        ]);

        DB::table('coupon_branches')->insert([
            'tenant_id' => $tenant->id,
            'coupon_id' => $restricted->id,
            'branch_id' => $otherBranch->id,
        ]);

        try {
            app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id) + [
                'coupon_code' => $restricted->code,
            ]);
            $this->fail('A coupon for another branch was accepted.');
        } catch (InvalidCoupon) {
            $this->assertSame(0, Order::query()->count());
        }
    }

    public function test_percent_coupon_applies_before_exclusive_tax(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        Coupon::query()->create([
            'code' => 'SAVE10',
            'kind' => 'percent',
            'value' => '10.00',
            'is_active' => true,
        ]);

        $order = app(CreateOrder::class)->handle($this->orderPayload($branch->id, $product->id) + [
            'coupon_code' => 'SAVE10',
        ]);

        $this->assertSame('10.00', $order->discount_total);
        $this->assertSame('90.00', $order->subtotal);
        $this->assertSame('12.60', $order->tax_total);
        $this->assertSame('102.60', $order->total);
        $this->assertDatabaseHas('coupon_redemptions', [
            'order_id' => $order->id,
        ]);
    }

    public function test_delivery_zone_fee_is_snapshotted_on_the_order(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $zone = DeliveryZone::query()->create([
            'branch_id' => $branch->id,
            'name_ar' => 'المعادي',
            'name_en' => 'Maadi',
            'fee' => '25.00',
            'min_order' => '50.00',
            'is_active' => true,
        ]);

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'delivery',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'delivery_zone_id' => $zone->id,
            'delivery_address' => ['line' => 'شارع 9'],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->assertSame('25.00', $order->delivery_fee);
        $this->assertSame('139.00', $order->total);
    }

    public function test_option_prices_are_taken_from_the_database_not_the_client(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $group = app(CreateOptionGroup::class)->handle($product, [
            'name_ar' => 'الحجم',
            'name_en' => 'Size',
            'kind' => 'size',
            'selection' => 'single',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);
        $option = app(CreateOption::class)->handle($group, [
            'name_ar' => 'كبير',
            'name_en' => 'Large',
            'price_delta' => '10.00',
        ]);

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'option_ids' => [$option->id],
                ],
            ],
        ]);

        $item = $order->items->first();
        $this->assertSame('10.00', $item->options_total);
        $this->assertSame('220.00', $order->subtotal);
        $this->assertSame('30.80', $order->tax_total);
        $this->assertSame('250.80', $order->total);
        $this->assertSame('كبير', $item->options->first()->name_ar);
        $this->assertSame('10.00', $item->options->first()->price_delta);
    }

    /**
     * @return array{0: \App\Models\Tenant, 1: \App\Models\Branch, 2: \App\Models\Product}
     */
    private function seedCatalog(string $slug): array
    {
        $created = app(CreateTenant::class)->handle($this->payload($slug));
        app(TenantContext::class)->set($created['tenant']->id);
        app(FeatureGate::class)->flush();

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

        return [$created['tenant']->fresh(), $created['branch'], $product];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(int $branchId, int $productId, ?string $key = null): array
    {
        return [
            'branch_id' => $branchId,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => $key ?? (string) Str::ulid(),
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ];
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
