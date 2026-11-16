<?php

namespace Database\Seeders;

use App\Domain\Subscription\StartSubscription;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\Features\FeatureCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'billing.grace_days' => 7,
            'platform.name' => 'Restaurant SaaS Platform',
            'maintenance.enabled' => false,
            'kitchen.poll_seconds' => 10,
        ] as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value]],
            );
        }

        $featureIds = [];

        foreach (FeatureCatalog::all() as $key => $definition) {
            $feature = Feature::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value_type' => $definition['type'],
                    'name_ar' => $definition['name_ar'],
                    'name_en' => $definition['name_en'],
                ],
            );
            $featureIds[$key] = $feature->id;
        }

        foreach ($this->plans() as $code => $definition) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name_ar' => $definition['name_ar'],
                    'name_en' => $definition['name_en'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'is_public' => true,
                    'trial_days' => $definition['trial_days'],
                    'sort_order' => $definition['sort_order'],
                ],
            );

            foreach (['monthly' => $definition['monthly'], 'yearly' => $definition['yearly']] as $interval => $amount) {
                $plan->prices()->updateOrCreate(
                    ['interval' => $interval, 'currency_code' => 'EGP'],
                    ['amount' => $amount, 'is_active' => true],
                );
            }

            $sync = [];

            foreach ($definition['features'] as $key => $value) {
                $sync[$featureIds[$key]] = ['value' => $value];
            }

            $plan->features()->sync($sync);
        }

        foreach ([
            'addon.extra_branch' => ['فرع إضافي', 'Extra branch', 200, 2000],
            'addon.extra_user' => ['مستخدم إضافي', 'Extra user', 50, 500],
            'addon.whatsapp' => ['واتساب', 'WhatsApp', 100, 1000],
        ] as $key => [$nameAr, $nameEn, $monthly, $yearly]) {
            $addonId = DB::table('addons')->where('key', $key)->value('id');

            if ($addonId === null) {
                $addonId = DB::table('addons')->insertGetId([
                    'key' => $key,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['monthly' => $monthly, 'yearly' => $yearly] as $interval => $amount) {
                DB::table('addon_prices')->updateOrInsert(
                    ['addon_id' => $addonId, 'interval' => $interval, 'currency_code' => 'EGP'],
                    ['amount' => $amount, 'is_active' => true],
                );
            }
        }

        foreach (Tenant::query()->whereDoesntHave('subscription')->get() as $tenant) {
            $branches = DB::table('branches')->where('tenant_id', $tenant->id)->count();
            $users = DB::table('users')->where('tenant_id', $tenant->id)->where('status', 'active')->count();
            app(TenantContext::class)->set($tenant->id);
            app(StartSubscription::class)->handle(
                $tenant,
                ($branches > 1 || $users > 3) ? 'pro' : 'starter',
                'monthly',
                false,
            );
        }

        app(TenantContext::class)->clear();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function plans(): array
    {
        $shared = [
            'module.loyalty' => '0',
            'products.max' => null,
        ];

        return [
            'starter' => [
                'name_ar' => 'البداية',
                'name_en' => 'Starter',
                'description' => 'فرع واحد وقائمة وطلب أونلاين. الأسعار مقترحة ويمكن تعديلها.',
                'trial_days' => 0,
                'sort_order' => 1,
                'monthly' => 499,
                'yearly' => 4990,
                'features' => $shared + [
                    'branches.max' => '1',
                    'users.max' => '3',
                    'orders.monthly' => '300',
                    'module.digital_menu' => '1',
                    'module.online_ordering' => '1',
                    'module.pos' => '0',
                    'module.inventory' => '0',
                    'module.reservations' => '0',
                    'module.kitchen' => '0',
                    'module.delivery' => '1',
                    'module.advanced_reports' => '0',
                    'module.multi_branch' => '0',
                    'images.per_product' => '2',
                ],
            ],
            'growth' => [
                'name_ar' => 'النمو',
                'name_en' => 'Growth',
                'description' => 'نقطة بيع ومطبخ وحجوزات، مع تجربة 14 يوماً.',
                'trial_days' => 14,
                'sort_order' => 2,
                'monthly' => 999,
                'yearly' => 9990,
                'features' => $shared + [
                    'branches.max' => '1',
                    'users.max' => '10',
                    'orders.monthly' => '2000',
                    'module.digital_menu' => '1',
                    'module.online_ordering' => '1',
                    'module.pos' => '1',
                    'module.inventory' => '0',
                    'module.reservations' => '1',
                    'module.kitchen' => '1',
                    'module.delivery' => '1',
                    'module.advanced_reports' => '0',
                    'module.multi_branch' => '0',
                    'images.per_product' => '6',
                ],
            ],
            'pro' => [
                'name_ar' => 'الاحتراف',
                'name_en' => 'Pro',
                'description' => 'عدة فروع ومخزون وتقارير متقدمة.',
                'trial_days' => 0,
                'sort_order' => 3,
                'monthly' => 1999,
                'yearly' => 19990,
                'features' => $shared + [
                    'branches.max' => '5',
                    'users.max' => '30',
                    'orders.monthly' => '10000',
                    'module.digital_menu' => '1',
                    'module.online_ordering' => '1',
                    'module.pos' => '1',
                    'module.inventory' => '1',
                    'module.reservations' => '1',
                    'module.kitchen' => '1',
                    'module.delivery' => '1',
                    'module.advanced_reports' => '1',
                    'module.multi_branch' => '1',
                    'images.per_product' => '6',
                ],
            ],
        ];
    }
}
