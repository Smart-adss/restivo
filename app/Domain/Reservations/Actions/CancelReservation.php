<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CancelReservation
{
    public function __construct(private FeatureGate $features) {}

    public function handle(Reservation $reservation, ?int $userId = null): Reservation
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('reservations.reservations.manage'), 403);

        return DB::transaction(function () use ($reservation, $userId): Reservation {
            /** @var Reservation $locked */
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [ReservationStatus::CANCELLED, ReservationStatus::COMPLETED, ReservationStatus::NO_SHOW], true)) {
                throw new HttpException(409, 'The reservation is already closed.');
            }

            $from = $locked->status;
            $locked->status = ReservationStatus::CANCELLED;
            $locked->save();

            ReservationStatusHistory::query()->create([
                'reservation_id' => $locked->id,
                'from_status' => $from,
                'to_status' => ReservationStatus::CANCELLED,
                'user_id' => $userId ?? auth()->id(),
                'created_at' => now(),
            ]);

            return $locked->fresh(['tables']);
        });
    }
}
