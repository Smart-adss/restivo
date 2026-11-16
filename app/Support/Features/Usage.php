<?php

namespace App\Support\Features;

use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class Usage
{
    public function __construct(private FeatureGate $gate) {}

    public function assertCanConsume(string $key, int $by = 1): void
    {
        $this->gate->assertWritable();
        $limit = $this->gate->limit($key);
        [$start, $end] = $this->period();

        DB::transaction(function () use ($key, $by, $limit, $start, $end): void {
            $counter = $this->lockCounter($key, $start, $end);

            if ($limit !== null && ($counter->used + $by) > $limit) {
                throw new LimitExceeded($key);
            }

            $counter->used += $by;
            $counter->save();
        });
    }

    public function used(string $key): int
    {
        [$start] = $this->period();

        return (int) UsageCounter::query()
            ->where('feature_key', $key)
            ->whereDate('period_start', $start)
            ->value('used');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function period(): array
    {
        $tenant = Tenant::query()->find(app(TenantContext::class)->id());
        $timezone = $tenant->timezone ?: 'Africa/Cairo';

        return [
            now($timezone)->startOfMonth()->toDateString(),
            now($timezone)->endOfMonth()->toDateString(),
        ];
    }

    private function lockCounter(string $key, string $start, string $end): UsageCounter
    {
        $counter = UsageCounter::query()
            ->where('feature_key', $key)
            ->whereDate('period_start', $start)
            ->lockForUpdate()
            ->first();

        if ($counter !== null) {
            return $counter;
        }

        try {
            $counter = UsageCounter::query()->create([
                'feature_key' => $key,
                'period_start' => $start,
                'period_end' => $end,
                'used' => 0,
            ]);
        } catch (QueryException) {
            return UsageCounter::query()
                ->where('feature_key', $key)
                ->whereDate('period_start', $start)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return UsageCounter::query()->whereKey($counter->id)->lockForUpdate()->firstOrFail();
    }
}
