<?php

namespace Tests\Feature;

use App\Domain\Finance\Actions\BuildBranchComparisonReport;
use App\Domain\Finance\Actions\BuildEstimatedProfitReport;
use App\Domain\Finance\Actions\FillDailyBranchSummaries;
use App\Domain\Inventory\Actions\CreateStockItem;
use App\Domain\Inventory\Actions\CreateUnit;
use App\Domain\Inventory\Actions\UpsertRecipe;
use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Pos\Actions\CreatePosSale;
use App\Domain\Pos\Actions\OpenShift;
use App\Domain\Subscription\UpsertSubscriptionAddon;
use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\CashRegister;
use App\Models\Feature;
use App\Models\TaxRate;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Sprint16ProfitAddonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_product_without_recipe_is_not_confirmed_profit(): void
    {
        [$owner, $branch, $register, $withRecipe, $withoutRecipe] = $this->seedProfitTenant('alpha');
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withoutRecipe->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withRecipe->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);

        $date = now('Africa/Cairo')->toDateString();
        $report = app(BuildEstimatedProfitReport::class)->handle($branch, $date, $date);

        $this->assertTrue($report['is_estimated']);
        $this->assertSame('100.00', $report['confirmed_sales']);
        $this->assertNotEmpty($report['missing_recipes']);
        $this->assertContains($withoutRecipe->name(), $report['missing_recipes']);
    }

    public function test_branch_comparison_requires_both_modules_and_reads_summaries(): void
    {
        [$owner, $branch, $register, $withRecipe] = array_slice($this->seedProfitTenant('beta'), 0, 4);
        $this->actingAs($owner);

        $shift = app(OpenShift::class)->handle($register->id, '0');
        app(CreatePosSale::class)->handle([
            'shift_id' => $shift->id,
            'idempotency_key' => (string) Str::ulid(),
            'items' => [['product_id' => $withRecipe->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '114.00']],
        ]);
        app(FillDailyBranchSummaries::class)->handle();

        $date = now('Africa/Cairo')->toDateString();
        $rows = app(BuildBranchComparisonReport::class)->handle($date, $date);
        $this->assertNotEmpty($rows);
        $this->assertSame(1, $rows[0]['orders_count']);

        $featureId = Feature::query()->where('key', 'module.multi_branch')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $owner->tenant->subscription->plan_id)
            ->update(['value' => '0']);
        app(FeatureGate::class)->flush();

        try {
            app(BuildBranchComparisonReport::class)->handle($date, $date);
            $this->fail('Comparison ran without multi_branch.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_addon_raises_effective_branch_limit_immediately(): void
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة gamma',
            'display_name_ar' => 'مطعم gamma',
            'display_name_en' => 'Restaurant gamma',
            'slug' => 'gamma',
            'email' => 'gamma@example.com',
            'phone' => '+201000000000',
            'owner_name' => 'مالك',
            'owner_email' => 'owner-gamma@example.com',
            'branch_name_ar' => 'رئيسي',
            'branch_name_en' => 'Main',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع',
            'plan_code' => 'starter',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);
        app(FeatureGate::class)->flush();

        $multiId = Feature::query()->where('key', 'module.multi_branch')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $multiId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '1']);
        app(FeatureGate::class)->flush();

        $this->assertSame(1, app(FeatureGate::class)->limit('branches.max'));

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);
        app(UpsertSubscriptionAddon::class)->handle($created['tenant']->subscription, 'addon.extra_branch', 1);
        app(FeatureGate::class)->flush();
        $this->assertSame(2, app(FeatureGate::class)->limit('branches.max'));

        $this->actingAs($created['owner']);
        app(FeatureGate::class)->flush();
        $second = app(CreateBranch::class)->handle([
            'name_ar' => 'فرع 2',
            'name_en' => 'B2',
            'code' => 'B2',
            'city' => 'الجيزة',
            'address' => 'الهرم',
        ]);
        $this->assertNotNull($second->id);
    }

    public function test_downgrade_message_mentions_excess_branches(): void
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة delta',
            'display_name_ar' => 'مطعم delta',
            'display_name_en' => 'Restaurant delta',
            'slug' => 'delta',
            'email' => 'delta@example.com',
            'phone' => '+201000000001',
            'owner_name' => 'مالك',
            'owner_email' => 'owner-delta@example.com',
            'branch_name_ar' => 'رئيسي',
            'branch_name_en' => 'Main',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع',
            'plan_code' => 'pro',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);
        $this->actingAs($created['owner']);
        app(CreateBranch::class)->handle([
            'name_ar' => 'فرع ثان',
            'name_en' => 'Second',
            'code' => 'B2',
            'city' => 'الجيزة',
            'address' => 'الهرم',
        ]);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $response = $this->actingAs($admin)->post(route('platform.tenants.plan.update', $created['tenant']->public_id), [
            'plan_code' => 'starter',
        ]);

        $response->assertSessionHasErrors('plan_code');
        $message = session('errors')->get('plan_code')[0];
        $this->assertStringContainsString('فروع', $message);
        $this->assertStringContainsString('2', $message);
    }

    public function test_loyalty_table_exists_without_grant_ui(): void
    {
        $this->assertTrue(Schema::hasTable('loyalty_transactions'));
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => str_contains((string) $route->getName(), 'loyalty')
            )
        );

        $html = file_get_contents(resource_path('views/public/menu/show.blade.php'));
        $this->assertStringNotContainsString('loyalty', strtolower($html));
        $this->assertStringNotContainsString('نقاط', $html);

        $this->assertSame(0, DB::table('loyalty_transactions')->count());
    }

    /**
     * @return array{0: User, 1: \App\Models\Branch, 2: CashRegister, 3: \App\Models\Product, 4: \App\Models\Product}
     */
    private function seedProfitTenant(string $slug): array
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة '.$slug,
            'display_name_ar' => 'مطعم '.$slug,
            'display_name_en' => 'Restaurant '.$slug,
            'slug' => $slug,
            'email' => $slug.'@example.com',
            'phone' => '+201000000010',
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

        foreach (['module.pos', 'module.inventory', 'module.advanced_reports', 'module.multi_branch'] as $key) {
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
        $withRecipe = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'tax_rate_id' => $tax->id,
            'name_ar' => 'شاي بوصفة',
            'name_en' => 'Tea recipe',
            'base_price' => '100.00',
        ]);
        $withoutRecipe = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'tax_rate_id' => $tax->id,
            'name_ar' => 'قهوة بلا وصفة',
            'name_en' => 'Coffee no recipe',
            'base_price' => '100.00',
        ]);

        $unit = app(CreateUnit::class)->handle([
            'name_ar' => 'جرام',
            'name_en' => 'Gram',
            'abbreviation' => 'g',
            'kind' => 'weight',
        ]);
        $item = app(CreateStockItem::class)->handle([
            'name_ar' => 'شاي',
            'name_en' => 'Tea',
            'unit_id' => $unit->id,
            'kind' => 'ingredient',
            'sku' => 'TEA-'.$slug,
        ]);
        app(UpsertRecipe::class)->handle($withRecipe, [
            'lines' => [[
                'stock_item_id' => $item->id,
                'quantity' => '2',
                'unit_id' => $unit->id,
            ]],
        ]);

        $register = CashRegister::query()->create([
            'branch_id' => $created['branch']->id,
            'name' => 'صندوق',
            'is_active' => true,
        ]);

        return [$owner, $created['branch'], $register, $withRecipe, $withoutRecipe];
    }
}
