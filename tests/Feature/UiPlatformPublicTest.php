<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Menu\Actions\StoreProductImage;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Feature;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UiPlatformPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Storage::fake('local');
    }

    public function test_platform_shell_shows_console_mark(): void
    {
        $admin = User::query()->where('is_platform_user', true)->firstOrFail();
        config(['restivo.require_platform_totp' => false]);

        $this->actingAs($admin)
            ->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee(__('app.platform_badge'), false)
            ->assertSee(__('app.platform_console'), false)
            ->assertSee('max-w-6xl', false);
    }

    public function test_public_menu_renders_category_nav_and_product_cards(): void
    {
        $created = $this->tenantWithMenu();

        $this->get(route('public.menu.show', [
            'slug' => $created['tenant']->slug,
            'branchPublicId' => $created['branch']->public_id,
        ]))
            ->assertOk()
            ->assertSee('public-cat-chip', false)
            ->assertSee('public-product', false)
            ->assertSee('لاتيه', false);
    }

    public function test_public_product_image_route_serves_jpeg(): void
    {
        $created = $this->tenantWithMenu();
        app(TenantContext::class)->set($created['tenant']->id);

        $path = storage_path('framework/testing/product.jpg');
        $image = imagecreatetruecolor(40, 40);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        app(StoreProductImage::class)->handle(
            $created['product'],
            new UploadedFile($path, 'product.jpg', 'image/jpeg', null, true),
            true,
        );

        $this->get(route('public.menu.product-image', [
            'slug' => $created['tenant']->slug,
            'productPublicId' => $created['product']->public_id,
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_totp_pages_use_guest_layout_components(): void
    {
        $admin = User::query()->where('is_platform_user', true)->firstOrFail();
        config(['restivo.require_platform_totp' => true]);

        $this->actingAs($admin)
            ->get(route('platform.totp.setup'))
            ->assertOk()
            ->assertSee(__('auth.totp_setup_title'), false)
            ->assertSee('ui-btn-primary', false);

        $admin->forceFill([
            'totp_secret' => \App\Support\Security\Totp::generateSecret(),
            'totp_confirmed_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->withSession(['platform_totp_ok' => false])
            ->get(route('platform.totp.challenge'))
            ->assertOk()
            ->assertSee(__('auth.totp_challenge_title'), false)
            ->assertSee('ui-btn-primary', false);
    }

    /**
     * @return array{tenant: \App\Models\Tenant, branch: \App\Models\Branch, product: \App\Models\Product}
     */
    private function tenantWithMenu(): array
    {
        $created = app(CreateTenant::class)->handle([
            'legal_name' => 'شركة قائمة',
            'display_name_ar' => 'مقهى عام',
            'display_name_en' => 'Public Cafe',
            'slug' => 'public-cafe',
            'email' => 'public-cafe@example.com',
            'phone' => '01000000000',
            'owner_name' => 'مالك القائمة',
            'owner_email' => 'owner-public@example.com',
            'branch_name_ar' => 'الفرع',
            'branch_name_en' => 'Branch',
            'branch_code' => 'MAIN',
            'city' => 'القاهرة',
            'address' => 'شارع',
            'plan_code' => 'growth',
        ]);

        app(TenantContext::class)->set($created['tenant']->id);

        foreach (['module.digital_menu', 'module.online_ordering'] as $key) {
            $featureId = Feature::query()->where('key', $key)->value('id');
            DB::table('plan_features')
                ->where('feature_id', $featureId)
                ->where('plan_id', $created['tenant']->subscription->plan_id)
                ->update(['value' => '1']);
        }
        app(FeatureGate::class)->flush();

        $category = app(CreateCategory::class)->handle([
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'لاتيه',
            'name_en' => 'Latte',
            'base_price' => '45.00',
            'is_active' => true,
        ]);

        return [
            'tenant' => $created['tenant'],
            'branch' => $created['branch'],
            'product' => $product,
        ];
    }
}
