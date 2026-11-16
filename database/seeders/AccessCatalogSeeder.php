<?php

namespace Database\Seeders;

use App\Domain\Identity\PermissionCatalog;
use App\Domain\Identity\RoleCatalog;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AccessCatalogSeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => 'EGP'],
            [
                'name_ar' => 'جنيه مصري',
                'name_en' => 'Egyptian pound',
                'symbol' => 'ج.م',
                'decimal_places' => 2,
                'is_active' => true,
            ],
        );

        foreach (PermissionCatalog::all() as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group_name' => explode('.', $key)[0],
                    'description' => $description,
                ],
            );
        }

        foreach (RoleCatalog::definitions() as $key => $definition) {
            $role = Role::withoutGlobalScope('tenant')->updateOrCreate(
                ['tenant_id' => null, 'key' => $key],
                [
                    'name_ar' => $definition['name_ar'],
                    'name_en' => $definition['name_en'],
                    'is_system' => true,
                ],
            );

            $role->permissions()->sync(
                Permission::query()->whereIn('key', $definition['grants'])->pluck('id'),
            );
        }
    }
}
