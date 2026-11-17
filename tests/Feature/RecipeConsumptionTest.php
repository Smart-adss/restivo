<?php

namespace Tests\Feature;

use App\Domain\Inventory\Actions\ConsumeOrderRecipes;
use App\Domain\Inventory\Actions\CreateStockItem;
use App\Domain\Inventory\Actions\CreateUnit;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\UpsertRecipe;
use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\BranchStock;
use App\Models\CashRegister;
use App\Models\Feature;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipeConsumptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_one_recipe_per_product_and_sale_consumes_ingredients(): void
    {
        [$owner, $register, $branch, $product, $item, $unit] = $this->seedRecipeTenant('alpha');
        $this->actingAs($owner);

        app(UpsertRecipe::class)->handle($product, [
            'yield_qty' => '1',
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '2.5',
                'unit_id' => $unit->id,
            ]],
        ]);

        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseCount('recipe_lines', 1);

        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '100',
            'note' => 'افتتاحي',
        ]);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        $sale = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => '228.00']],
        ]);

        $this->assertSame('completed', $sale['order']->status);
        $this->assertSame(1, StockMovement::query()->where('kind', 'sale')->count());
        $this->assertSame(
            '-5.000',
            (string) StockMovement::query()->where('kind', 'sale')->value('quantity')
        );
        $this->assertSame(
            '95.000',
            (string) BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('stock_item_id', $item->id)
                ->value('quantity')
        );
    }

    public function test_product_without_recipe_creates_no_sale_movement(): void
    {
        [$owner, $register, $branch, $product] = $this->seedRecipeTenant('beta');
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->assertSame(0, StockMovement::query()->where('kind', 'sale')->count());
    }

    public function test_consume_job_idempotency_key_prevents_duplicate_movements(): void
    {
        [$owner, $register, $branch, $product, $item, $unit] = $this->seedRecipeTenant('gamma');
        $this->actingAs($owner);

        app(UpsertRecipe::class)->handle($product, [
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '1',
                'unit_id' => $unit->id,
            ]],
        ]);
        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '50',
            'note' => 'افتتاحي',
        ]);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        $sale = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->assertSame(1, StockMovement::query()->where('kind', 'sale')->count());

        app(ConsumeOrderRecipes::class)->handle($sale['order']->fresh(['items']));

        $this->assertSame(1, StockMovement::query()->where('kind', 'sale')->count());
        $this->assertSame(
            '49.000',
            (string) BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('stock_item_id', $item->id)
                ->value('quantity')
        );
    }

    public function test_consumption_failure_keeps_sale_and_records_failed_job(): void
    {
        [$owner, $register, $branch, $product, $item, $unit] = $this->seedRecipeTenant('delta');
        $this->actingAs($owner);

        $otherUnit = app(CreateUnit::class)->handle([
            'name_ar' => 'لتر',
            'name_en' => 'Litre',
            'abbreviation' => 'L',
            'kind' => 'volume',
        ]);

        app(UpsertRecipe::class)->handle($product, [
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '1',
                'unit_id' => $otherUnit->id,
            ]],
        ]);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        $sale = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $this->assertSame('completed', $sale['order']->fresh()->status);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, StockMovement::query()->where('kind', 'sale')->count());
        $this->assertGreaterThanOrEqual(1, DB::table('failed_jobs')->count());
    }

    public function test_block_on_stockout_defaults_off_and_blocks_when_enabled(): void
    {
        [$owner, $register, $branch, $product, $item, $unit] = $this->seedRecipeTenant('epsilon');
        $this->actingAs($owner);

        app(UpsertRecipe::class)->handle($product, [
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '10',
                'unit_id' => $unit->id,
            ]],
        ]);

        $shift = app(OpenShift::class)->handle($register->id, '0');

        $allowed = app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);
        $this->assertSame('completed', $allowed['order']->status);

        DB::table('branch_settings')->updateOrInsert(
            [
                'branch_id' => $branch->id,
                'key' => 'inventory.block_on_stockout',
            ],
            [
                'tenant_id' => app(TenantContext::class)->id(),
                'value' => json_encode(true),
            ],
        );

        try {
            app(CreatePosSale::class)->handle([
                'shift_id' => $shift->id,
                'idempotency_key' => (string) Str::ulid(),
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount' => '114.00']],
            ]);
            $this->fail('Sale should be blocked when stockout setting is on.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(1, Order::query()->count());
    }

    /**
     * @return array{0: User, 1: CashRegister, 2: \App\Models\Branch, 3: \App\Models\Product, 4: \App\Models\StockItem, 5: \App\Models\Unit}
     */
    private function seedRecipeTenant(string $slug): array
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
            'plan_code' => 'pro',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);
        app(FeatureGate::class)->flush();

        foreach (['module.pos', 'module.inventory'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
        }
        app(FeatureGate::class)->flush();

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();
        $this->actingAs($owner);

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

        $unit = app(CreateUnit::class)->handle([
            'name_ar' => 'جرام',
            'name_en' => 'Gram',
            'abbreviation' => 'g',
            'kind' => 'weight',
        ]);
        $item = app(CreateStockItem::class)->handle([
            'name_ar' => 'شاي ورق',
            'name_en' => 'Tea leaves',
            'unit_id' => $unit->id,
            'kind' => 'ingredient',
            'sku' => 'TEA-'.$slug,
        ]);

        $register = CashRegister::query()->create([
            'branch_id' => $created['branch']->id,
            'name' => 'صندوق 1',
            'is_active' => true,
        ]);

        return [$owner, $register, $created['branch'], $product, $item, $unit];
    }
}
