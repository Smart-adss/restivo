<?php

namespace App\Domain\Tenancy\Actions;

use App\Models\Branch;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class CreateBranch
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{name_ar: string, name_en: string, code: string, city: string, address: string, phone?: ?string}  $data
     */
    public function handle(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $active = Branch::query()->where('is_active', true)->count();

            if ($active >= 1 && ! $this->features->allows('module.multi_branch')) {
                throw new LimitExceeded('module.multi_branch');
            }

            $this->features->assertUnderCeiling('branches.max', $active);

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
