<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateOption;
use App\Domain\Menu\Actions\CreateOptionGroup;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Pos\Actions\CloseShift;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Pos\Actions\RecordCashMovement;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\CashRegister;
use App\Models\CashierShift;
use App\Models\Feature;
use App\Models\Order;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_second_open_shift_on_same_register_returns_409(): void
    {
        [$owner, $register] = $this->seedPos('alpha');
        $this->actingAs($owner);

        app(OpenShift::class)->handle($register->id, '100.00');

        try {
            app(OpenShift::class)->handle($register->id, '50.00');
            $this->fail('A second open shift was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(1, CashierShift::query()->where('status', 'open')->count());
    }

    public function test_cash_sale_and_close_shift_match_expected_cash(): void
    {
        [$owner, $register, $branch, $product] = $this->seedPos('alpha');
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '100.00');
        app(RecordCashMovement::class)->handle($shift, 'pay_in', '20.00', 'float top-up');

        $key = (string) Str::ulid();
        $first = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => $key,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [
                ['method' => 'cash', 'amount' => '114.00'],
            ],
        ]);

        $this->assertSame('completed', $first['order']->status);
        $this->assertSame('114.00', (string) $first['order']->total);

        $again = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => $key,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [
                ['method' => 'cash', 'amount' => '114.00'],
            ],
        ]);

        $this->assertTrue($first['order']->is($again['order']));
        $this->assertSame(1, Order::query()->count());

        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [
                ['method' => 'card', 'amount' => '50.00'],
                ['method' => 'cash', 'amount' => '64.00'],
            ],
        ]);

        $closed = app(CloseShift::class)->handle($shift->fresh(), '298.00');
        // opening 100 + pay_in 20 + cash sales 114 + 64 = 298
        $this->assertSame('298.00', (string) $closed->expected_cash);
        $this->assertSame('298.00', (string) $closed->counted_cash);
        $this->assertSame('0.00', (string) $closed->difference);
    }

    public function test_payment_mismatch_returns_422_with_server_total(): void
    {
        [$owner, $register, $branch, $product] = $this->seedPos('alpha');
        $this->actingAs($owner);
        $shift = app(OpenShift::class)->handle($register->id, '0');

        $response = $this->postJson(route('app.pos.sales.store'), [
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [
                ['method' => 'cash', 'amount' => '10.00'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.fields.total', '114.00');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_required_options_block_sale_without_selection(): void
    {
        [$owner, $register, $branch, $product] = $this->seedPos('alpha');
        $this->actingAs($owner);

        $group = app(CreateOptionGroup::class)->handle($product, [
            'name_ar' => 'الحجم',
            'name_en' => 'Size',
            'kind' => 'size',
            'selection' => 'single',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);
        app(CreateOption::class)->handle($group, [
            'name_ar' => 'كبير',
            'name_en' => 'Large',
            'price_delta' => '10.00',
        ]);

        $shift = app(OpenShift::class)->handle($register->id, '0');

        $this->expectException(\App\Domain\Ordering\Support\ProductUnavailable::class);

        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'option_ids' => []]],
            'payments' => [['method' => 'cash', 'amount' => '100.00']],
        ]);
    }

    public function test_receipt_preview_shows_number_and_total_in_arabic(): void
    {
        [$owner, $register, $branch, $product] = $this->seedPos('alpha');
        $this->actingAs($owner);
        app()->setLocale('ar');

        $shift = app(OpenShift::class)->handle($register->id, '0');
        $sale = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->get(route('app.pos.receipt', $sale['order']->public_id))
            ->assertOk()
            ->assertSee('#'.$sale['order']->number)
            ->assertSee('114.00')
            ->assertSee('رقم الفاتورة');
    }

    /**
     * @return array{0: User, 1: CashRegister, 2: \App\Models\Branch, 3: \App\Models\Product}
     */
    private function seedPos(string $slug): array
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

        $featureId = Feature::query()->where('key', 'module.pos')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '1']);
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

        $register = CashRegister::query()->create([
            'branch_id' => $created['branch']->id,
            'name' => 'صندوق 1',
            'is_active' => true,
        ]);

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();

        return [$owner, $register, $created['branch'], $product];
    }
}
