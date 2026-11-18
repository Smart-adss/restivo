<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use App\Support\Ui\SidebarNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_login_uses_foundation_components(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ui-btn', false)
            ->assertSee('ui-field', false)
            ->assertSee(config('app.name'), false);
    }

    public function test_tenant_shell_renders_grouped_navigation(): void
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة alpha',
            'display_name_ar' => 'مطعم alpha',
            'display_name_en' => 'Restaurant alpha',
            'slug' => 'alpha',
            'email' => 'alpha@example.com',
            'phone' => '01000000000',
            'owner_name' => 'مالك alpha',
            'owner_email' => 'owner-alpha@example.com',
            'branch_name_ar' => 'الفرع الرئيسي',
            'branch_name_en' => 'Main branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع التحرير',
            'plan_code' => 'growth',
        ]);

        $this->actingAs($created['owner'])
            ->get(route('app.home'))
            ->assertOk()
            ->assertSee(__('app.nav_workspace'), false)
            ->assertSee(__('app.workspace_home_title'), false)
            ->assertSee('ui-shell-aside', false);
    }

    public function test_platform_shell_renders_platform_nav_group(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee(__('app.platform_title'), false)
            ->assertSee(__('app.tenants_index_hint'), false)
            ->assertSee('ui-nav-link', false);
    }

    public function test_sidebar_nav_groups_exclude_hidden_modules_for_starter(): void
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة beta',
            'display_name_ar' => 'مطعم beta',
            'display_name_en' => 'Restaurant beta',
            'slug' => 'beta',
            'email' => 'beta@example.com',
            'phone' => '01000000001',
            'owner_name' => 'مالك beta',
            'owner_email' => 'owner-beta@example.com',
            'branch_name_ar' => 'الفرع الرئيسي',
            'branch_name_en' => 'Main branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع التحرير',
            'plan_code' => 'starter',
        ]);

        $this->actingAs($created['owner']);
        app(TenantContext::class)->set($created['tenant']->id);
        app(FeatureGate::class)->flush();

        $labels = collect(SidebarNav::groups($created['owner']))->pluck('label')->all();

        $this->assertContains(__('app.nav_workspace'), $labels);
        $this->assertNotContains(__('app.nav_inventory'), $labels);
    }
}
