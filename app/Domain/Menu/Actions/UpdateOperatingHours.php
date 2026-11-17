<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\Branch;
use App\Models\OperatingHour;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOperatingHours
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  list<array{day_of_week: int, is_closed: bool, opens_at?: ?string, closes_at?: ?string}>  $days
     */
    public function handle(Branch $branch, array $days): void
    {
        $this->features->assertWritable();

        if (count($days) !== 7) {
            throw ValidationException::withMessages([
                'days' => __('app.operating_hours_incomplete'),
            ]);
        }

        $tenantId = app(TenantContext::class)->id();

        DB::transaction(function () use ($branch, $days, $tenantId): void {
            foreach ($days as $day) {
                $dayOfWeek = (int) $day['day_of_week'];
                $isClosed = (bool) $day['is_closed'];
                $opens = $isClosed ? null : ($day['opens_at'] ?? null);
                $closes = $isClosed ? null : ($day['closes_at'] ?? null);

                if (! $isClosed && ($opens === null || $closes === null)) {
                    throw ValidationException::withMessages([
                        'days' => __('app.operating_hours_incomplete'),
                    ]);
                }

                $updated = OperatingHour::query()
                    ->where('branch_id', $branch->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->update([
                        'is_closed' => $isClosed,
                        'opens_at' => $opens,
                        'closes_at' => $closes,
                    ]);

                if ($updated === 0) {
                    DB::table('operating_hours')->insert([
                        'tenant_id' => $tenantId,
                        'branch_id' => $branch->id,
                        'day_of_week' => $dayOfWeek,
                        'opens_at' => $opens,
                        'closes_at' => $closes,
                        'is_closed' => $isClosed ? 1 : 0,
                    ]);
                }
            }
        });

        PublicMenuCache::forget($branch->id);
    }
}
