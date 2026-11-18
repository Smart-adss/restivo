<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Feature;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UiCatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_catalog_inventory_and_reports_use_shared_page_header(): void
    {
        $created = $this->seedGrowthTenant('catalog');

        $this->actingAs($created['owner'])
            ->get(route('app.menu.products.index'))
            ->assertOk()
            ->assertSee(__('app.products_catalog_hint'), false);

        $this->actingAs($created['owner'])
            ->get(route('app.menu.categories.index'))
            ->assertOk()
            ->assertSee(__('app.categories_catalog_hint'), false);

        $this->actingAs($created['owner'])
            ->get(route('app.inventory.index'))
            ->assertOk()
            ->assertSee(__('app.inventory_page_hint'), false);

        $this->actingAs($created['owner'])
            ->get(route('app.reports.day'))
            ->assertOk()
            ->assertSee(__('app.day_report_hint'), false)
            ->assertSee('ui-panel', false);
    }

    public function test_floor_uses_ops_layout(): void
    {
        $created = $this->seedGrowthTenant('floor');

        $this->actingAs($created['owner'])
            ->get(route('app.tables.index'))
            ->assertOk()
            ->assertSee(__('app.back_to_workspace'), false)
            ->assertDontSee('ui-shell-aside', false);
    }

    /**
     * @return array{owner: \App\Models\User, tenant: \App\Models\Tenant}
     */
    private function seedGrowthTenant(string $slug): array
    {
        $created = app(CreateTenant::class)->handle([
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
            'plan_code' => 'growth',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);

        foreach (['module.pos', 'module.kitchen', 'module.inventory', 'module.online_ordering', 'module.reservations'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            if ($featureId) {
                DB::table('plan_features')
                    ->where('feature_id', $featureId)
                    ->where('plan_id', $created['tenant']->subscription->plan_id)
                    ->update(['value' => '1']);
            }
        }
        app(FeatureGate::class)->flush();

        return $created;
    }
}
