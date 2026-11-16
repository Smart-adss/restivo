<?php

namespace App\Domain\Tenancy\Actions;

use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class CreateBranch
{
    /**
     * @param  array{name_ar: string, name_en: string, code: string, city: string, address: string, phone?: ?string}  $data
     */
    public function handle(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $branch = Branch::query()->create([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'code' => $data['code'],
                'city' => $data['city'],
                'address' => $data['address'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $tenantId = app(TenantContext::class)->id();

            foreach (range(0, 6) as $day) {
                DB::table('operating_hours')->insert([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branch->id,
                    'day_of_week' => $day,
                    'opens_at' => '09:00',
                    'closes_at' => '23:00',
                    'is_closed' => false,
                ]);
            }

            return $branch;
        });
    }
}
