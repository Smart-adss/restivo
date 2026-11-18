<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Feature;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UiOpsSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_pos_and_kitchen_use_ops_layout_without_sidebar(): void
    {
        $created = $this->seedGrowthTenant('ops');

        $this->actingAs($created['owner'])
            ->get(route('app.pos'))
            ->assertOk()
            ->assertSee(__('app.back_to_workspace'), false)
            ->assertSee('max-w-[1600px]', false)
            ->assertDontSee('ui-shell-aside', false);

        $this->actingAs($created['owner'])
            ->get(route('app.kitchen.index'))
            ->assertOk()
            ->assertSee(__('app.back_to_workspace'), false)
            ->assertDontSee('ui-shell-aside', false);
    }

    public function test_home_shows_real_shortcuts_and_subscription_without_fake_kpis(): void
    {
        $created = $this->seedGrowthTenant('home');

        $this->actingAs($created['owner'])
            ->get(route('app.home'))
            ->assertOk()
            ->assertSee(__('app.workspace_home_title'), false)
            ->assertSee(__('app.home_quick_actions'), false)
            ->assertSee(__('app.pos'), false)
            ->assertSee(__('app.subscription'), false)
            ->assertDontSee('999999');
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

        foreach (['module.pos', 'module.kitchen', 'module.online_ordering'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
        }
        app(FeatureGate::class)->flush();

        return $created;
    }
}
