<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $hasRate = DB::table('tax_rates')->where('tenant_id', $tenantId)->exists();

            if ($hasRate) {
                continue;
            }

            DB::table('tax_rates')->insert([
                'tenant_id' => $tenantId,
                'public_id' => (string) Str::ulid(),
                'name' => 'ضريبة القيمة المضافة',
                'rate_percent' => '14.000',
                'is_inclusive' => false,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tax_rates')
            ->where('name', 'ضريبة القيمة المضافة')
            ->where('rate_percent', '14.000')
            ->where('is_default', true)
            ->delete();
    }
};
