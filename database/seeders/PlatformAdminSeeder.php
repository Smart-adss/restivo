<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مشرف المنصة',
                'password' => 'ChangeMe123',
                'locale' => 'ar',
                'status' => 'active',
                'is_platform_user' => true,
            ],
        );

        $role = Role::withoutGlobalScope('tenant')
            ->whereNull('tenant_id')
            ->where('key', 'platform_super_admin')
            ->first();

        if ($role && ! DB::table('user_roles')->where('user_id', $user->id)->where('role_id', $role->id)->exists()) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'branch_id' => null,
            ]);
        }
    }
}
