<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MarkReservationNoShow
{
    public function __construct(private FeatureGate $features) {}

    public function handle(Reservation $reservation, ?int $userId = null): Reservation
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('reservations.reservations.manage'), 403);

        return DB::transaction(function () use ($reservation, $userId): Reservation {
            /** @var Reservation $locked */
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReservationStatus::CONFIRMED) {
                throw new HttpException(409, 'Only confirmed reservations can be marked no-show.');
            }

            $timezone = Tenant::query()->find(app(TenantContext::class)->id())?->timezone ?: 'Africa/Cairo';
            $reservedLocal = Carbon::parse($locked->reserved_at->format('Y-m-d H:i:s'), $timezone);

            if ($reservedLocal->gt(Carbon::now($timezone))) {
                throw new HttpException(409, 'No-show is only allowed after the reservation time.');
            }

            $from = $locked->status;
            $locked->status = ReservationStatus::NO_SHOW;
            $locked->save();

            ReservationStatusHistory::query()->create([
                'reservation_id' => $locked->id,
                'from_status' => $from,
                'to_status' => ReservationStatus::NO_SHOW,
                'user_id' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            return $locked->fresh(['tables']);
        });
    }
}
