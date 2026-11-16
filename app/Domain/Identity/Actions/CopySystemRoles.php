<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\RoleCatalog;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class CopySystemRoles
{
    public function copyTo(Tenant $tenant): void
    {
        if (app(TenantContext::class)->id() !== $tenant->id) {
            app(TenantContext::class)->set($tenant->id);
        }

        $templates = Role::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('is_system', true)
            ->whereIn('key', RoleCatalog::tenantTemplateKeys())
            ->get();

        foreach ($templates as $template) {
            $exists = Role::query()->where('key', $template->key)->exists();

            if ($exists) {
                continue;
            }

            $copy = Role::query()->create([
                'key' => $template->key,
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'is_system' => true,
            ]);

            $permissionIds = DB::table('role_permission')
                ->where('role_id', $template->id)
                ->pluck('permission_id');
            $copy->permissions()->sync($permissionIds);
        }
    }
}
