<?php

namespace Tests\Feature;

use App\Domain\Inventory\Actions\CreateStockItem;
use App\Domain\Inventory\Actions\CreateUnit;
use App\Domain\Inventory\Actions\CreateUnitConversion;
use App\Domain\Inventory\Actions\RecalculateBranchStocks;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\BranchStock;
use App\Models\Feature;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_circular_unit_conversion_is_rejected(): void
    {
        [$owner] = $this->seedInventory('alpha');
        $this->actingAs($owner);

        $kg = app(CreateUnit::class)->handle([
            'name_ar' => 'كيلو',
            'name_en' => 'Kilogram',
            'abbreviation' => 'kg',
            'kind' => 'weight',
        ]);
        $g = app(CreateUnit::class)->handle([
            'name_ar' => 'جرام',
            'name_en' => 'Gram',
            'abbreviation' => 'g',
            'kind' => 'weight',
        ]);

        app(CreateUnitConversion::class)->handle([
            'from_unit_id' => $kg->id,
            'to_unit_id' => $g->id,
            'factor' => '1000',
        ]);

        try {
            app(CreateUnitConversion::class)->handle([
                'from_unit_id' => $g->id,
                'to_unit_id' => $kg->id,
                'factor' => '0.001',
            ]);
            $this->fail('Circular conversion was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_unit_id', $exception->errors());
        }
    }

    public function test_balance_matches_ledger_sum_and_movements_cannot_be_deleted(): void
    {
        [$owner, $branch, $item] = $this->seedInventory('alpha');
        $this->actingAs($owner);

        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '100',
            'note' => 'افتتاحي',
        ]);
        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'waste',
            'quantity' => '12.5',
            'note' => 'تالف',
        ]);
        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '-3.25',
            'note' => 'تصحيح',
        ]);

        $ledger = number_format((float) StockMovement::query()
            ->where('branch_id', $branch->id)
            ->where('stock_item_id', $item->id)
            ->sum('quantity'), 3, '.', '');

        $balance = (string) BranchStock::query()
            ->where('branch_id', $branch->id)
            ->where('stock_item_id', $item->id)
            ->value('quantity');

        $this->assertSame('84.250', $ledger);
        $this->assertSame('84.250', $balance);

        $movement = StockMovement::query()->first();

        try {
            $movement->delete();
            $this->fail('Movement delete was allowed.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertSame(3, StockMovement::query()->count());
    }

    public function test_recalculate_restores_balance_from_ledger(): void
    {
        [$owner, $branch, $item] = $this->seedInventory('alpha');
        $this->actingAs($owner);

        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '40',
            'note' => 'افتتاحي',
        ]);

        BranchStock::query()
            ->where('branch_id', $branch->id)
            ->where('stock_item_id', $item->id)
            ->update(['quantity' => '1.000']);

        app(RecalculateBranchStocks::class)->handle($branch->id);

        $this->assertSame(
            '40.000',
            (string) BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('stock_item_id', $item->id)
                ->value('quantity')
        );
    }

    public function test_plan_without_inventory_module_hides_screen_and_blocks_action(): void
    {
        [$owner, $branch] = $this->seedInventory('alpha', enableModule: false);
        $this->actingAs($owner);

        $this->get(route('app.inventory.index', ['branch_id' => $branch->id]))->assertNotFound();

        try {
            app(CreateUnit::class)->handle([
                'name_ar' => 'قطعة',
                'name_en' => 'Piece',
                'abbreviation' => 'pc',
                'kind' => 'count',
            ]);
            $this->fail('Inventory action ran without the module.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    /**
     * @return array{0: User, 1: \App\Models\Branch, 2?: \App\Models\StockItem}
     */
    private function seedInventory(string $slug, bool $enableModule = true): array
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
            'plan_code' => $enableModule ? 'pro' : 'growth',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);
        app(FeatureGate::class)->flush();

        $featureId = Feature::query()->where('key', 'module.inventory')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => $enableModule ? '1' : '0']);
        app(FeatureGate::class)->flush();

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();

        if (! $enableModule) {
            return [$owner, $created['branch']];
        }

        $this->actingAs($owner);
        $unit = app(CreateUnit::class)->handle([
            'name_ar' => 'قطعة',
            'name_en' => 'Piece',
            'abbreviation' => 'pc',
            'kind' => 'count',
        ]);
        $item = app(CreateStockItem::class)->handle([
            'name_ar' => 'سكر',
            'name_en' => 'Sugar',
            'unit_id' => $unit->id,
            'kind' => 'ingredient',
            'sku' => 'SUGAR-1',
        ]);

        return [$owner, $created['branch'], $item];
    }
}
