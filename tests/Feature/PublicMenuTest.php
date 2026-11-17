<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\ActivateMenuTheme;
use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Menu\Actions\EnsureMenuQrCode;
use App\Domain\Menu\Actions\UpdateOperatingHours;
use App\Domain\Menu\Actions\UpsertProductBranchSetting;
use App\Domain\Menu\Support\MenuThemeCatalog;
use App\Domain\Menu\Support\PublicMenuCache;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Feature;
use App\Models\MenuTheme;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Storage::fake('local');
    }

    public function test_public_menu_renders_in_arabic_for_an_active_branch(): void
    {
        $created = $this->tenantWithProduct();

        $this->get(route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]))
            ->assertOk()
            ->assertSee('لاتيه', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_theme_activation_only_applies_allowed_token_keys(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);

        app(ActivateMenuTheme::class)->handle('ocean');

        $theme = MenuTheme::query()->where('is_active', true)->first();
        $tokens = $theme->tokens;

        $this->assertSame('ocean', $theme->preset_key);
        $this->assertEqualsCanonicalizing(MenuThemeCatalog::ALLOWED_TOKEN_KEYS, array_keys($tokens));
        $this->assertSame(MenuThemeCatalog::all()['ocean']['tokens']['brand'], $tokens['brand']);
        $this->assertArrayNotHasKey('background-image', $tokens);
    }

    public function test_menu_qr_opens_the_same_branch_url(): void
    {
        $created = $this->tenantWithProduct();
        app(TenantContext::class)->set($created['tenant']->id);

        $qr = app(EnsureMenuQrCode::class)->handle($created['branch']);
        $png = Storage::disk('local')->get($qr->image_path);

        $this->assertSame('menu', $qr->target_type);
        $this->assertSame($created['branch']->public_id, $qr->target_public_id);
        $this->assertSame("\x89PNG", substr($png, 0, 4));

        $this->get(route('public.menu.qr', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_suspended_tenant_or_closed_feature_returns_404_and_unavailable_products_are_hidden(): void
    {
        $created = $this->tenantWithProduct();
        $url = route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]);

        app(TenantContext::class)->set($created['tenant']->id);
        app(UpsertProductBranchSetting::class)->handle($created['product'], $created['branch'], [
            'is_available' => false,
            'price_override' => null,
        ]);

        $this->get($url)->assertOk()->assertDontSee('لاتيه', false);

        $created['tenant']->update(['status' => 'suspended']);
        PublicMenuCache::forget($created['branch']->id);
        $this->get($url)->assertNotFound();

        $created['tenant']->update(['status' => 'active']);
        $featureId = Feature::query()->where('key', 'module.digital_menu')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '0']);
        app(\App\Support\Features\FeatureGate::class)->flush();
        PublicMenuCache::forget($created['branch']->id);

        $this->get($url)->assertNotFound();
    }

    public function test_public_menu_cache_is_cleared_when_price_changes(): void
    {
        $created = $this->tenantWithProduct();
        $url = route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]);

        $this->get($url)->assertOk()->assertSee('45.00', false);
        $this->assertTrue(Cache::has(PublicMenuCache::key($created['branch']->id)));

        app(TenantContext::class)->set($created['tenant']->id);
        app(UpsertProductBranchSetting::class)->handle($created['product'], $created['branch'], [
            'is_available' => true,
            'price_override' => '60.00',
        ]);

        $this->assertFalse(Cache::has(PublicMenuCache::key($created['branch']->id)));
        $this->get($url)->assertOk()->assertSee('60.00', false);
    }

    public function test_closed_day_is_shown_as_closed_to_the_guest(): void
    {
        $created = $this->tenantWithProduct();
        app(TenantContext::class)->set($created['tenant']->id);

        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', 'Africa/Cairo')); // Monday
        $days = [];

        foreach (range(0, 6) as $day) {
            $days[] = [
                'day_of_week' => $day,
                'is_closed' => $day === 0,
                'opens_at' => '09:00',
                'closes_at' => '23:00',
            ];
        }

        app(UpdateOperatingHours::class)->handle($created['branch'], $days);

        $this->get(route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]))
            ->assertOk()
            ->assertSee(__('app.branch_closed_now'), false);

        Carbon::setTestNow();
    }

    /**
     * @return array{tenant: \App\Models\Tenant, owner: \App\Models\User, branch: \App\Models\Branch, product: \App\Models\Product}
     */
    private function tenantWithProduct(): array
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        app(TenantContext::class)->set($created['tenant']->id);
        $category = app(CreateCategory::class)->handle([
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
        ]);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'لاتيه',
            'name_en' => 'Latte',
            'base_price' => '45.00',
        ]);

        return $created + ['product' => $product];
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
