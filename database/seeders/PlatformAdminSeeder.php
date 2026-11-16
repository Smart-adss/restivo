<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مشرف المنصة',
                'password' => 'ChangeMe123',
                'locale' => 'ar',
                'status' => 'active',
                'is_platform_user' => true,
            ],
        );
    }
}
