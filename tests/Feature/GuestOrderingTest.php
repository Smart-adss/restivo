<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Ordering\Actions\CancelOrder;
use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Ordering\Actions\TransitionOrderStatus;
use App\Domain\Ordering\Actions\UpsertCustomerByPhone;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\TaxRate;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GuestOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_quote_and_place_order_use_server_prices_not_client_prices(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $quote = $this->postJson(route('api.public.orders.quote', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'pickup',
            'items' => [
                ['product_id' => $product->public_id, 'quantity' => 1],
            ],
        ])->assertOk()->json('data');

        $this->assertSame('100.00', $quote['subtotal']);
        $this->assertSame('14.00', $quote['tax_total']);
        $this->assertSame('114.00', $quote['total']);

        $key = (string) Str::ulid();
        $created = $this->postJson(route('api.public.orders.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'pickup',
            'customer_name' => 'أحمد',
            'customer_phone' => '+201012345678',
            'idempotency_key' => $key,
            'items' => [
                ['product_id' => $product->public_id, 'quantity' => 1],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame('114.00', $created['total']);
        $this->assertSame(1, Customer::query()->where('phone', '+201012345678')->count());

        $this->postJson(route('api.public.orders.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'pickup',
            'customer_name' => 'أحمد',
            'customer_phone' => '+201012345678',
            'idempotency_key' => $key,
            'items' => [
                ['product_id' => $product->public_id, 'quantity' => 1],
            ],
        ])->assertCreated()->assertJsonPath('data.number', $created['number']);

        $this->assertSame(1, Order::query()->count());
    }

    public function test_delivery_without_zone_is_rejected_and_zone_fee_is_quoted(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $this->postJson(route('api.public.orders.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'delivery',
            'customer_name' => 'سارة',
            'customer_phone' => '+201011111111',
            'address_line' => 'شارع 9',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [
                ['product_id' => $product->public_id, 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $zone = DeliveryZone::query()->create([
            'branch_id' => $branch->id,
            'name_ar' => 'المعادي',
            'name_en' => 'Maadi',
            'fee' => '25.00',
            'is_active' => true,
        ]);

        $this->postJson(route('api.public.orders.quote', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'delivery',
            'zone_id' => $zone->public_id,
            'items' => [
                ['product_id' => $product->public_id, 'quantity' => 1],
            ],
        ])->assertOk()->assertJsonPath('data.delivery_fee', '25.00')->assertJsonPath('data.total', '139.00');
    }

    public function test_customers_are_scoped_per_tenant_by_phone(): void
    {
        [$firstTenant] = $this->seedCatalog('alpha');
        app(UpsertCustomerByPhone::class)->handle('+201022222222', 'منشأة أ');

        $second = app(CreateTenant::class)->handle($this->payload('beta'));
        app(TenantContext::class)->set($second['tenant']->id);
        app(FeatureGate::class)->flush();
        app(UpsertCustomerByPhone::class)->handle('+201022222222', 'منشأة ب');

        app(TenantContext::class)->set($firstTenant->id);
        $this->assertSame(1, Customer::query()->where('phone', '+201022222222')->count());
        $this->assertSame('منشأة أ', Customer::query()->where('phone', '+201022222222')->value('name'));

        app(TenantContext::class)->set($second['tenant']->id);
        $this->assertSame(1, Customer::query()->where('phone', '+201022222222')->count());
        $this->assertSame('منشأة ب', Customer::query()->where('phone', '+201022222222')->value('name'));
        $this->assertSame(2, Customer::withoutGlobalScopes()->where('phone', '+201022222222')->count());
    }

    public function test_tracking_requires_matching_phone_and_wrong_phone_is_404(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'أحمد',
            'customer_phone' => '+201033333333',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->get(route('public.orders.track', [
            'slug' => $tenant->slug,
            'orderPublicId' => $order->public_id,
            'phone' => '+201033333333',
        ]))->assertOk()->assertSee('#'.$order->number);

        $this->get(route('public.orders.track', [
            'slug' => $tenant->slug,
            'orderPublicId' => $order->public_id,
            'phone' => '+201099999999',
        ]))->assertNotFound();

        $this->getJson(route('api.public.orders.show', [
            'slug' => $tenant->slug,
            'orderPublicId' => $order->public_id,
            'phone' => '+201099999999',
        ]))->assertNotFound();
    }

    public function test_status_skip_is_rejected_and_cancel_keeps_the_row(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');
        $ownerEmail = 'owner-alpha@example.com';

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'أحمد',
            'customer_phone' => '+201044444444',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $owner = \App\Models\User::query()->where('email', $ownerEmail)->firstOrFail();
        $this->actingAs($owner);

        try {
            app(TransitionOrderStatus::class)->handle($order, 'preparing');
            $this->fail('A skipped status transition was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        app(TransitionOrderStatus::class)->handle($order->fresh(), 'accepted');
        app(CancelOrder::class)->handle($order->fresh());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(1, Order::query()->count());
    }

    public function test_public_order_rate_limit_returns_429(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('api.public.orders.store', $tenant->slug), [
                'branch_id' => $branch->public_id,
                'type' => 'pickup',
                'customer_name' => 'أحمد',
                'customer_phone' => '+20105555555'.($i % 10),
                'idempotency_key' => (string) Str::ulid(),
                'items' => [
                    ['product_id' => $product->public_id, 'quantity' => 1],
                ],
            ])->assertCreated();
        }

        $this->postJson(route('api.public.orders.store', $tenant->slug), [
            'branch_id' => $branch->public_id,
            'type' => 'pickup',
            'customer_name' => 'أحمد',
            'customer_phone' => '+201066666666',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [
                ['product_id' => $product->public_id, 'quantity' => 1],
            ],
        ])->assertStatus(429);
    }

    public function test_orders_board_is_visible_to_the_owner(): void
    {
        [$tenant, $branch, $product] = $this->seedCatalog('alpha');
        $owner = \App\Models\User::query()->where('email', 'owner-alpha@example.com')->firstOrFail();

        app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'أحمد',
            'customer_phone' => '+201077777777',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $this->actingAs($owner)
            ->get(route('app.orders.index'))
            ->assertOk()
            ->assertSee('#1');
    }

    public function test_public_menu_shows_order_controls_when_online_ordering_is_enabled(): void
    {
        [$tenant, $branch] = $this->seedCatalog('alpha');

        $this->get(route('public.menu.show', [
            'slug' => $tenant->slug,
            'branchPublicId' => $branch->public_id,
        ]))->assertOk()->assertSee(__('app.place_order'), false);
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
