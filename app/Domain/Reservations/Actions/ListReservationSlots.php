<?php

namespace App\Domain\Reservations\Actions;

use App\Models\Branch;
use App\Models\OperatingHour;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ListReservationSlots
{
    public const DURATION_MINUTES = 90;

    public const SLOT_STEP_MINUTES = 30;

    public function __construct(private FeatureGate $features) {}

    /**
     * @return list<string> ISO local datetimes
     */
    public function handle(Branch $branch, string $date, bool $enforceLeadTime = true): array
    {
        if (! $this->features->allows('module.reservations')) {
            abort(404);
        }

        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());
        $timezone = $tenant->timezone ?: 'Africa/Cairo';

        try {
            $day = Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date' => 'Invalid date.',
            ]);
        }

        $hours = OperatingHour::query()
            ->where('branch_id', $branch->id)
            ->where('day_of_week', $day->dayOfWeekIso - 1)
            ->first();

        if ($hours === null || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            return [];
        }

        $open = Carbon::parse($day->toDateString().' '.$hours->opens_at, $timezone);
        $close = Carbon::parse($day->toDateString().' '.$hours->closes_at, $timezone);
        $minStart = $enforceLeadTime
            ? Carbon::now($timezone)->addHour()
            : $open->copy();

        $slots = [];
        $cursor = $open->copy();

        while ($cursor->copy()->addMinutes(self::DURATION_MINUTES)->lte($close)) {
            if ($cursor->gte($minStart)) {
                $slots[] = $cursor->toDateTimeString();
            }
            $cursor->addMinutes(self::SLOT_STEP_MINUTES);
        }

        return $slots;
    }
}
