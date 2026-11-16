<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Identity\Actions\CopySystemRoles;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateTenant
{
    public function __construct(
        private CopySystemRoles $copySystemRoles,
        private CreateBranch $createBranch,
    ) {}

    /**
     * @param  array<string, string>  $data
     * @return array{tenant: Tenant, owner: User, branch: Branch, password: string}
     */
    public function handle(array $data): array
    {
        if (! Currency::query()->where('code', 'EGP')->exists()) {
            throw new RuntimeException('Currency EGP is not seeded.');
        }

        return DB::transaction(function () use ($data): array {
            $tenant = Tenant::query()->create([
                'slug' => $data['slug'],
                'legal_name' => $data['legal_name'],
                'display_name_ar' => $data['display_name_ar'],
                'display_name_en' => $data['display_name_en'],
                'status' => 'active',
                'timezone' => 'Africa/Cairo',
                'currency_code' => 'EGP',
                'default_locale' => 'ar',
                'email' => $data['email'],
                'phone' => $data['phone'],
            ]);

            app(TenantContext::class)->set($tenant->id);
            $this->copySystemRoles->copyTo($tenant);
            $this->storeDefaultSettings($tenant->id);

            $branch = $this->createBranch->handle([
                'name_ar' => $data['branch_name_ar'],
                'name_en' => $data['branch_name_en'],
                'code' => $data['branch_code'],
                'city' => $data['city'],
                'address' => $data['address'],
                'phone' => $data['phone'],
            ]);

            $password = Str::password(12, symbols: false);
            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $password,
                'phone' => $data['phone'],
                'locale' => 'ar',
                'status' => 'active',
                'is_platform_user' => false,
            ]);
            $owner->forceFill(['tenant_id' => $tenant->id])->save();

            $ownerRole = Role::query()->where('key', 'business_owner')->first();

            if ($ownerRole === null) {
                throw new RuntimeException('The business owner role was not copied.');
            }

            DB::table('user_roles')->insert([
                'user_id' => $owner->id,
                'role_id' => $ownerRole->id,
                'branch_id' => null,
            ]);

            DB::table('branch_user')->insert([
                'user_id' => $owner->id,
                'branch_id' => $branch->id,
                'is_primary' => true,
            ]);

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'branch' => $branch,
                'password' => $password,
            ];
        });
    }

    private function storeDefaultSettings(int $tenantId): void
    {
        $settings = [
            'tax.prices_include_tax' => false,
            'order.allow_outside_hours' => false,
            'order.service_charge_percent' => 0,
            'kitchen.late_after_minutes' => 20,
            'pos.require_shift' => true,
            'inventory.block_sale_when_out' => false,
        ];

        foreach ($settings as $key => $value) {
            DB::table('tenant_settings')->insert([
                'tenant_id' => $tenantId,
                'key' => $key,
                'value' => json_encode($value),
            ]);
        }
    }
}
