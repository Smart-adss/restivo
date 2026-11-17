<?php

namespace Tests\Feature;

use App\Domain\Inventory\Actions\CreateStockCount;
use App\Domain\Inventory\Actions\CreateStockItem;
use App\Domain\Inventory\Actions\CreateStockTransfer;
use App\Domain\Inventory\Actions\CreateSupplier;
use App\Domain\Inventory\Actions\CreateUnit;
use App\Domain\Inventory\Actions\PostStockCount;
use App\Domain\Inventory\Actions\ReceivePurchaseOrder;
use App\Domain\Inventory\Actions\ReceiveStockTransfer;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\SendStockTransfer;
use App\Domain\Inventory\Actions\UpsertPurchaseOrder;
use App\Domain\Tenancy\Actions\CreateBranch;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_draft_purchase_order_is_editable_and_partial_receive_updates_avg_cost(): void
    {
        [$owner, $branch, $item] = $this->seedProcurement('alpha');
        $this->actingAs($owner);

        $supplier = app(CreateSupplier::class)->handle([
            'name' => 'مورد الشاي',
            'phone' => '+201111111111',
        ]);

        $order = app(UpsertPurchaseOrder::class)->handle([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '10',
                'unit_cost' => '5.0000',
            ]],
        ]);

        $this->assertSame('draft', $order->status);
        $this->assertSame('50.00', (string) $order->total);

        $order = app(UpsertPurchaseOrder::class)->handle([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '20',
                'unit_cost' => '4.0000',
            ]],
        ], $order);

        $this->assertSame('draft', $order->status);
        $this->assertSame('80.00', (string) $order->total);
        $this->assertSame(1, $order->lines()->count());

        app(ReceivePurchaseOrder::class)->handle($order, [
            ['line_id' => $order->lines->first()->id, 'quantity' => '8'],
        ]);

        $order->refresh();
        $this->assertSame('partial', $order->status);
        $this->assertSame('8.000', (string) $order->lines()->first()->received_qty);

        $stock = BranchStock::query()
            ->where('branch_id', $branch->id)
            ->where('stock_item_id', $item->id)
            ->firstOrFail();
        $this->assertSame('8.000', (string) $stock->quantity);
        $this->assertSame('4.0000', (string) $stock->avg_cost);

        app(ReceivePurchaseOrder::class)->handle($order->fresh(['lines']), [
            ['line_id' => $order->lines->first()->id, 'quantity' => '12'],
        ]);

        $order->refresh();
        $this->assertSame('received', $order->status);

        try {
            app(ReceivePurchaseOrder::class)->handle($order->fresh(['lines']), [
                ['line_id' => $order->lines->first()->id, 'quantity' => '1'],
            ]);
            $this->fail('Over-receive was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(2, StockMovement::query()->where('kind', 'purchase')->count());
        $firstCost = (string) StockMovement::query()->where('kind', 'purchase')->orderBy('id')->value('unit_cost');
        $this->assertSame('4.0000', $firstCost);
    }

    public function test_receive_rejects_quantity_above_remaining(): void
    {
        [$owner, $branch, $item] = $this->seedProcurement('beta');
        $this->actingAs($owner);

        $supplier = app(CreateSupplier::class)->handle([
            'name' => 'مورد',
            'phone' => '+201222222222',
        ]);
        $order = app(UpsertPurchaseOrder::class)->handle([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '5',
                'unit_cost' => '2',
            ]],
        ]);

        try {
            app(ReceivePurchaseOrder::class)->handle($order, [
                ['line_id' => $order->lines->first()->id, 'quantity' => '6'],
            ]);
            $this->fail('Over-remaining receive was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.quantity', $exception->errors());
        }

        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_count_post_writes_difference_once(): void
    {
        [$owner, $branch, $item] = $this->seedProcurement('gamma');
        $this->actingAs($owner);

        app(RecordStockMovement::class)->handle([
            'branch_id' => $branch->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '10',
            'note' => 'افتتاحي',
        ]);

        $count = app(CreateStockCount::class)->handle([
            'branch_id' => $branch->id,
            'lines' => [[
                'stock_item_id' => $item->id,
                'counted_qty' => '7',
            ]],
        ]);

        $this->assertSame('draft', $count->status);
        $this->assertSame('10.000', (string) $count->lines->first()->expected_qty);

        app(PostStockCount::class)->handle($count);

        $this->assertSame('posted', $count->fresh()->status);
        $this->assertSame(1, StockMovement::query()->where('kind', 'count')->count());
        $this->assertSame('-3.000', (string) StockMovement::query()->where('kind', 'count')->value('quantity'));
        $this->assertSame(
            '7.000',
            (string) BranchStock::query()
                ->where('branch_id', $branch->id)
                ->where('stock_item_id', $item->id)
                ->value('quantity')
        );

        try {
            app(PostStockCount::class)->handle($count->fresh());
            $this->fail('Second post was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(1, StockMovement::query()->where('kind', 'count')->count());
    }

    public function test_transfer_creates_equal_out_and_in_and_fails_across_tenants(): void
    {
        [$owner, $branchA, $item] = $this->seedProcurement('delta');
        $this->actingAs($owner);

        $branchB = app(CreateBranch::class)->handle([
            'name_ar' => 'فرع ثان',
            'name_en' => 'Second',
            'code' => 'SEC',
            'city' => 'الجيزة',
            'address' => 'شارع الهرم',
        ]);

        app(RecordStockMovement::class)->handle([
            'branch_id' => $branchA->id,
            'stock_item_id' => $item->id,
            'kind' => 'adjustment',
            'quantity' => '15',
            'note' => 'افتتاحي',
        ]);

        $transfer = app(CreateStockTransfer::class)->handle([
            'from_branch_id' => $branchA->id,
            'to_branch_id' => $branchB->id,
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '4',
            ]],
        ]);

        app(SendStockTransfer::class)->handle($transfer);
        app(ReceiveStockTransfer::class)->handle($transfer->fresh());

        $out = StockMovement::query()->where('kind', 'transfer_out')->first();
        $in = StockMovement::query()->where('kind', 'transfer_in')->first();
        $this->assertSame('-4.000', (string) $out->quantity);
        $this->assertSame('4.000', (string) $in->quantity);
        $this->assertSame($branchA->id, $out->branch_id);
        $this->assertSame($branchB->id, $in->branch_id);

        $other = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة أخرى',
            'display_name_ar' => 'مطعم آخر',
            'display_name_en' => 'Other',
            'slug' => 'other-tenant',
            'email' => 'other@example.com',
            'phone' => '+201000000099',
            'owner_name' => 'مالك آخر',
            'owner_email' => 'owner-other@example.com',
            'branch_name_ar' => 'فرع',
            'branch_name_en' => 'Branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'عنوان',
            'plan_code' => 'pro',
        ]);
        $foreignBranchId = $other['branch']->id;

        app(TenantContext::class)->set($owner->tenant_id);
        app(FeatureGate::class)->flush();
        $this->actingAs($owner);

        try {
            app(CreateStockTransfer::class)->handle([
                'from_branch_id' => $branchA->id,
                'to_branch_id' => $foreignBranchId,
                'lines' => [[
                    'stock_item_id' => $item->id,
                    'quantity' => '1',
                ]],
            ]);
            $this->fail('Cross-tenant transfer was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to_branch_id', $exception->errors());
        }
    }

    /**
     * @return array{0: User, 1: \App\Models\Branch, 2: \App\Models\StockItem}
     */
    private function seedProcurement(string $slug): array
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

        foreach (['module.inventory', 'module.multi_branch'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
        }
        app(FeatureGate::class)->flush();

        $owner = User::query()->where('email', 'owner-'.$slug.'@example.com')->firstOrFail();
        $this->actingAs($owner);

        $unit = app(CreateUnit::class)->handle([
            'name_ar' => 'كيس',
            'name_en' => 'Bag',
            'abbreviation' => 'bag',
            'kind' => 'count',
        ]);
        $item = app(CreateStockItem::class)->handle([
            'name_ar' => 'سكر',
            'name_en' => 'Sugar',
            'unit_id' => $unit->id,
            'kind' => 'ingredient',
            'sku' => 'SUGAR-'.$slug,
        ]);

        return [$owner, $created['branch'], $item];
    }
}
