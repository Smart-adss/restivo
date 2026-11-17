<?php

namespace Tests\Feature;

use App\Domain\Menu\Actions\CreateCategory;
use App\Domain\Menu\Actions\CreateProduct;
use App\Domain\Ordering\Actions\CreateOrder;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Models\Feature;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_platform_admin_sees_live_usage_against_plan_limits(): void
    {
        $created = app(CreateTenant::class)->handle($this->payload('alpha') + ['plan_code' => 'growth']);
        app(TenantContext::class)->set($created['tenant']->id);

        $featureId = Feature::query()->where('key', 'module.online_ordering')->value('id');
        DB::table('plan_features')
            ->where('feature_id', $featureId)
            ->where('plan_id', $created['tenant']->subscription->plan_id)
            ->update(['value' => '1']);
        app(FeatureGate::class)->flush();

        $category = app(CreateCategory::class)->handle([
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
        ]);
        $product = app(CreateProduct::class)->handle([
            'category_id' => $category->id,
            'name_ar' => 'شاي',
            'name_en' => 'Tea',
            'base_price' => '100.00',
        ]);

        app(CreateOrder::class)->handle([
            'branch_id' => $created['branch']->id,
            'type' => 'pickup',
            'source' => 'online',
            'idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'أحمد',
            'customer_phone' => '+201012345678',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'status' => 'placed',
        ]);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $branchLimit = app(FeatureGate::class)->limit('branches.max');
        $userLimit = app(FeatureGate::class)->limit('users.max');
        $orderLimit = app(FeatureGate::class)->limit('orders.monthly');

        $this->actingAs($admin)
            ->get(route('platform.tenants.show', $created['tenant']->public_id))
            ->assertOk()
            ->assertSee('1 / '.($branchLimit ?? __('app.unlimited')), false)
            ->assertSee('1 / '.($userLimit ?? __('app.unlimited')), false)
            ->assertSee('1 / '.($orderLimit ?? __('app.unlimited')), false);

        $this->actingAs($admin)
            ->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee($created['tenant']->subscription->plan->name(), false);
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
