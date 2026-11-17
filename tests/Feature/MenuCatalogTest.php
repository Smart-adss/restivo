<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateOption;
use App\Domain\Menu\Actions\CreateOptionGroup;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Menu\Actions\StoreProductImage;
use App\Domain\Menu\Actions\UpsertProductBranchSetting;
use App\Domain\Menu\Support\ConflictingDefaultOption;
use App\Domain\Tenancy\Actions\CreateBranch;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Feature;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Support\Features\LimitExceeded;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Storage::fake('local');
    }

    public function test_composite_foreign_key_rejects_a_branch_price_from_another_tenant(): void
    {
        $first = app(CreateTenant::class)->handle($this->payload('alpha'));
        $second = app(CreateTenant::class)->handle($this->payload('beta'));

        app(TenantContext::class)->set($first['tenant']->id);
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

        try {
            DB::table('product_branch_settings')->insert([
                'tenant_id' => $second['tenant']->id,
                'product_id' => $product->id,
                'branch_id' => $first['branch']->id,
                'is_available' => 1,
                'price_override' => '50.00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('A composite foreign key accepted a mismatched tenant price.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('product_branch_settings', [
                'product_id' => $product->id,
            ]);
        }
    }

    public function test_empty_categories_are_hidden_from_the_public_menu_scope(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);

        $empty = app(CreateCategory::class)->handle([
            'name_ar' => 'فارغ',
            'name_en' => 'Empty',
        ]);
        $filled = app(CreateCategory::class)->handle([
            'name_ar' => 'جاهز',
            'name_en' => 'Ready',
        ]);
        app(CreateProduct::class)->handle([
            'category_id' => $filled->id,
            'name_ar' => 'شاي',
            'name_en' => 'Tea',
            'base_price' => '10.00',
        ]);

        $visible = MenuCategory::query()->visibleOnPublicMenu()->pluck('id');

        $this->assertTrue($visible->contains($filled->id));
        $this->assertFalse($visible->contains($empty->id));
    }

    public function test_product_validation_messages_are_localized_in_arabic(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app()->setLocale('ar');

        $this->actingAs($created['owner'])
            ->from(route('app.menu.products.create'))
            ->post(route('app.menu.products.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'category_id',
                'name_ar',
                'name_en',
                'base_price',
            ]);

        $errors = session('errors');
        $this->assertStringContainsString('الاسم بالعربية', $errors->first('name_ar'));
        $this->assertStringContainsString('السعر الأساسي', $errors->first('base_price'));
    }

    public function test_single_selection_group_rejects_a_second_default_option(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);
        $category = app(CreateCategory::class)->handle(['name_ar' => 'أ', 'name_en' => 'A']);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'لاتيه',
            'name_en' => 'Latte',
            'base_price' => '40.00',
        ]);
        $group = app(CreateOptionGroup::class)->handle($product, [
            'name_ar' => 'الحجم',
            'name_en' => 'Size',
            'kind' => 'size',
            'selection' => 'single',
            'is_required' => true,
        ]);

        app(CreateOption::class)->handle($group, [
            'name_ar' => 'وسط',
            'name_en' => 'Medium',
            'is_default' => true,
        ]);

        $this->expectException(ConflictingDefaultOption::class);

        app(CreateOption::class)->handle($group, [
            'name_ar' => 'كبير',
            'name_en' => 'Large',
            'is_default' => true,
        ]);
    }

    public function test_branch_without_settings_inherits_price_and_availability(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'pro']);
        app(TenantContext::class)->set($created['tenant']->id);
        $second = app(CreateBranch::class)->handle([
            'name_ar' => 'فرع المعادي',
            'name_en' => 'Maadi',
            'code' => 'MAADI',
            'city' => 'القاهرة',
            'address' => 'شارع',
        ]);

        $category = app(CreateCategory::class)->handle(['name_ar' => 'أ', 'name_en' => 'A']);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'لاتيه',
            'name_en' => 'Latte',
            'base_price' => '45.00',
        ]);

        app(UpsertProductBranchSetting::class)->handle($product, $created['branch'], [
            'is_available' => false,
            'price_override' => '55.00',
        ]);

        $product->load('branchSettings');

        $this->assertFalse($product->isAvailableAt($created['branch']));
        $this->assertSame('55.00', $product->priceAt($created['branch']));
        $this->assertTrue($product->isAvailableAt($second));
        $this->assertSame('45.00', $product->priceAt($second));
    }

    public function test_gd_rejects_php_disguised_as_jpg_and_caps_width_at_1600(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);
        $category = app(CreateCategory::class)->handle(['name_ar' => 'أ', 'name_en' => 'A']);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'لاتيه',
            'name_en' => 'Latte',
            'base_price' => '45.00',
        ]);

        $fakePhp = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo 1;');

        $this->actingAs($created['owner'])
            ->post(route('app.menu.products.images.store', $product->public_id), [
                'image' => $fakePhp,
            ])
            ->assertSessionHasErrors('image');

        $wide = imagecreatetruecolor(2400, 800);
        $path = sys_get_temp_dir().'/wide-'.uniqid().'.jpg';
        imagejpeg($wide, $path, 90);
        imagedestroy($wide);

        $image = app(StoreProductImage::class)->handle(
            $product,
            new UploadedFile($path, 'wide.jpg', 'image/jpeg', null, true),
        );

        $stored = Storage::disk('local')->path($image->path);
        $size = getimagesize($stored);

        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(1600, max($size[0], $size[1]));
        @unlink($path);
    }

    public function test_products_max_rejects_a_new_product_at_the_ceiling(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);
        $featureId = Feature::query()->where('key', 'products.max')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '1']);

        $category = app(CreateCategory::class)->handle(['name_ar' => 'أ', 'name_en' => 'A']);
        app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'أول',
            'name_en' => 'First',
            'base_price' => '10.00',
        ]);

        $this->expectException(LimitExceeded::class);

        app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'ثاني',
            'name_en' => 'Second',
            'base_price' => '12.00',
        ]);
    }

    public function test_images_per_product_rejects_extra_uploads(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha'));
        app(TenantContext::class)->set($created['tenant']->id);
        $featureId = Feature::query()->where('key', 'images.per_product')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '1']);

        $category = app(CreateCategory::class)->handle(['name_ar' => 'أ', 'name_en' => 'A']);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'لاتيه',
            'name_en' => 'Latte',
            'base_price' => '45.00',
        ]);

        $first = imagecreatetruecolor(100, 100);
        $firstPath = sys_get_temp_dir().'/img1-'.uniqid().'.jpg';
        imagejpeg($first, $firstPath, 90);
        imagedestroy($first);

        app(StoreProductImage::class)->handle(
            $product,
            new UploadedFile($firstPath, 'one.jpg', 'image/jpeg', null, true),
        );

        $second = imagecreatetruecolor(120, 120);
        $secondPath = sys_get_temp_dir().'/img2-'.uniqid().'.jpg';
        imagejpeg($second, $secondPath, 90);
        imagedestroy($second);

        try {
            app(StoreProductImage::class)->handle(
                $product,
                new UploadedFile($secondPath, 'two.jpg', 'image/jpeg', null, true),
            );
            $this->fail('The image ceiling was ignored.');
        } catch (LimitExceeded) {
            $this->assertSame(1, $product->images()->count());
        } finally {
            @unlink($firstPath);
            @unlink($secondPath);
        }
    }

    public function test_owner_can_manage_categories_and_products_over_http(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);

        $this->actingAs($created['owner'])
            ->post(route('app.menu.categories.store'), [
                'name_ar' => 'مشروبات',
                'name_en' => 'Drinks',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('app.menu.categories.index'));

        $category = MenuCategory::query()->first();

        $this->actingAs($created['owner'])
            ->post(route('app.menu.products.store'), [
                'category_id' => $category->id,
                'name_ar' => 'لاتيه',
                'name_en' => 'Latte',
                'base_price' => '45.50',
                'description_ar' => 'حليب وإسبريسو',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name_ar' => 'لاتيه',
            'base_price' => '45.50',
        ]);

        $product = Product::query()->first();

        $this->actingAs($created['owner'])
            ->get(route('app.menu.products.show', $product->public_id))
            ->assertOk()
            ->assertSee('لاتيه');
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
