<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Ordering\Actions\ApplyManualDiscount;
use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\CreateRefund;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Tables\Actions\CreateDiningTable;
use App\Domain\Tables\Actions\OpenTableSession;
use App\Domain\Tables\Actions\SplitBill;
use App\Domain\Tables\Actions\TransferTableSession;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\DiningTable;
use App\Models\Feature;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\Refund;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TableFloorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_table_name_must_be_unique_inside_branch(): void
    {
        [$owner, $branch] = $this->seedFloor('alpha');
        $this->actingAs($owner);

        app(CreateDiningTable::class)->handle([
            'branch_id' => $branch->id,
            'name' => 'A1',
            'seats' => 4,
        ]);

        try {
            app(CreateDiningTable::class)->handle([
                'branch_id' => $branch->id,
                'name' => 'A1',
                'seats' => 2,
            ]);
            $this->fail('Duplicate table name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $this->assertSame(1, DiningTable::query()->where('branch_id', $branch->id)->where('name', 'A1')->count());
    }

    public function test_dine_in_order_links_to_open_session(): void
    {
        [$owner, $branch, $product] = $this->seedFloor('alpha');
        $this->actingAs($owner);

        $table = app(CreateDiningTable::class)->handle([
            'branch_id' => $branch->id,
            'name' => 'T1',
            'seats' => 4,
        ]);
        $session = app(OpenTableSession::class)->handle($table, 2);

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'dine_in',
            'source' => 'pos',
            'idempotency_key' => (string) Str::ulid(),
            'table_session_id' => $session->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'status' => 'placed',
        ]);

        $this->assertSame($session->id, (int) $order->table_session_id);
        $this->assertTrue(
            DB::table('table_session_orders')
                ->where('table_session_id', $session->id)
                ->where('order_id', $order->id)
                ->exists()
        );
        $this->assertSame('occupied', $table->fresh()->status);
    }

    public function test_table_qr_from_other_branch_returns_404(): void
    {
        [$owner, $branch, $product] = $this->seedFloor('alpha');
        $this->actingAs($owner);

        $createdB = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة beta',
            'display_name_ar' => 'مطعم beta',
            'display_name_en' => 'Restaurant beta',
            'slug' => 'beta',
            'email' => 'beta@example.com',
            'phone' => '+201000000001',
            'owner_name' => 'مالك beta',
            'owner_email' => 'owner-beta@example.com',
            'branch_name_ar' => 'الفرع',
            'branch_name_en' => 'Branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع',
            'plan_code' => 'growth',
        ]);

        app(TenantContext::class)->set($createdB['tenant']->id);
        app(FeatureGate::class)->flush();
        $this->enableOnlineOrdering();

        $otherTable = DiningTable::query()->create([
            'branch_id' => $createdB['branch']->id,
            'name' => 'X9',
            'seats' => 2,
            'status' => 'available',
        ]);

        app(TenantContext::class)->set(
            \App\Models\Tenant::query()->where('slug', 'alpha')->value('id')
        );
        app(FeatureGate::class)->flush();

        $this->get(route('public.table.show', [
            'slug' => 'alpha',
            'tablePublicId' => $otherTable->public_id,
        ]))->assertNotFound();

        $this->postJson(route('api.public.table.orders.store', [
            'slug' => 'alpha',
            'tablePublicId' => $otherTable->public_id,
        ]), [
            'branch_id' => $branch->public_id,
            'customer_name' => 'ضيف',
            'customer_phone' => '+201012345678',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ])->assertNotFound();
    }

    public function test_transfer_to_occupied_table_is_rejected(): void
    {
        [$owner, $branch] = $this->seedFloor('alpha');
        $this->actingAs($owner);

        $a = app(CreateDiningTable::class)->handle([
            'branch_id' => $branch->id,
            'name' => 'A',
            'seats' => 2,
        ]);
        $b = app(CreateDiningTable::class)->handle([
            'branch_id' => $branch->id,
            'name' => 'B',
            'seats' => 2,
        ]);

        $sessionA = app(OpenTableSession::class)->handle($a, 1);
        app(OpenTableSession::class)->handle($b, 1);

        try {
            app(TransferTableSession::class)->handle($sessionA, $b->id);
            $this->fail('Transfer to occupied table was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame($a->id, (int) $sessionA->fresh()->table_id);
    }

    public function test_equal_split_uses_known_rounding_and_mismatch_is_rejected(): void
    {
        [$owner, $branch, $product] = $this->seedFloor('alpha');
        $this->actingAs($owner);

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'dine_in',
            'source' => 'pos',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'status' => 'placed',
        ]);

        // Force a roundable total of 100.00 for the acceptance check.
        $order->update([
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'delivery_fee' => '0.00',
            'service_charge' => '0.00',
            'total' => '100.00',
        ]);

        $this->assertSame(['33.33', '33.33', '33.34'], Money::split('100.00', 3));

        $split = app(SplitBill::class)->handle($order->fresh(), [
            'mode' => 'equal',
            'count' => 3,
        ]);

        $this->assertSame(
            ['33.33', '33.33', '33.34'],
            $split->parts->pluck('amount')->map(fn ($v) => (string) $v)->all()
        );

        try {
            app(SplitBill::class)->handle($order->fresh(), [
                'mode' => 'amount',
                'parts' => [
                    ['label' => 'A', 'amount' => '40.00'],
                    ['label' => 'B', 'amount' => '40.00'],
                ],
            ]);
            $this->fail('Mismatched split was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parts', $exception->errors());
        }
    }

    public function test_refund_cannot_exceed_remaining_and_keeps_original_sale(): void
    {
        [$owner, $branch, $product, $register] = $this->seedFloor('alpha', withPos: true);
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        $sale = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $order = $sale['order'];
        $payment = $order->payments->first();

        app(CreateRefund::class)->handle($order, [
            'payment_id' => $payment->id,
            'amount' => '50.00',
            'reason' => 'جزئي',
            'idempotency_key' => (string) Str::ulid(),
        ]);

        try {
            app(CreateRefund::class)->handle($order, [
                'payment_id' => $payment->id,
                'amount' => '70.00',
                'reason' => 'زيادة',
                'idempotency_key' => (string) Str::ulid(),
            ]);
            $this->fail('Over-refund was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(1, Order::query()->whereKey($order->id)->count());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
        $this->assertSame('114.00', (string) $order->fresh()->total);
    }

    public function test_manual_discount_is_stored_and_audited(): void
    {
        [$owner, $branch, $product] = $this->seedFloor('alpha');
        $this->actingAs($owner);

        $order = app(CreateOrder::class)->handle([
            'branch_id' => $branch->id,
            'type' => 'dine_in',
            'source' => 'pos',
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'status' => 'placed',
        ]);

        $beforeTotal = (string) $order->total;

        $discount = app(ApplyManualDiscount::class)->handle($order, '10.00', 'مجاملة مدير');

        $this->assertSame('manual', $discount->kind);
        $this->assertSame('مجاملة مدير', $discount->label);
        $this->assertTrue(
            OrderDiscount::query()->where('order_id', $order->id)->where('kind', 'manual')->exists()
        );
        $this->assertSame(Money::sub($beforeTotal, '10.00'), (string) $order->fresh()->total);
        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'order.manual_discount')
                ->where('auditable_id', $order->id)
                ->exists()
        );
    }

    /**
     * @return array{0: User, 1: \App\Models\Branch, 2?: \App\Models\Product, 3?: CashRegister}
     */
    private function seedFloor(string $slug, bool $withPos = false): array
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
        $this->enableOnlineOrdering();

        if ($withPos) {
            $featureId = Feature::query()->where('key', 'module.pos')->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
            app(FeatureGate::class)->flush();
        }

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

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();

        $register = null;
        if ($withPos) {
            $register = CashRegister::query()->create([
                'branch_id' => $created['branch']->id,
                'name' => 'صندوق 1',
                'is_active' => true,
            ]);
        }

        return $register
            ? [$owner, $created['branch'], $product, $register]
            : [$owner, $created['branch'], $product];
    }

    private function enableOnlineOrdering(): void
    {
        $tenant = \App\Models\Tenant::query()->find(app(TenantContext::class)->id());
        $featureId = Feature::query()->where('key', 'module.online_ordering')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $tenant->subscription->plan_id)
            ->update(['value' => '1']);
        app(FeatureGate::class)->flush();
    }
}
