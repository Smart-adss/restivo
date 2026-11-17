<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Messaging\Actions\QueueOutboundMessage;
use App\Domain\Reservations\ReservationStatus;
use App\Domain\Reservations\Support\TableConflict;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConfirmReservation
{
    public function __construct(
        private FeatureGate $features,
        private QueueOutboundMessage $queueOutbound,
    ) {}

    /**
     * @param  list<int>  $tableIds
     */
    public function handle(Reservation $reservation, array $tableIds = [], ?int $userId = null): Reservation
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('reservations.reservations.manage'), 403);

        $confirmed = DB::transaction(function () use ($reservation, $tableIds, $userId): Reservation {
            /** @var Reservation $locked */
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ReservationStatus::PENDING, ReservationStatus::CONFIRMED], true)) {
                throw new HttpException(409, 'Only pending or confirmed reservations can be confirmed with tables.');
            }

            if ($tableIds !== []) {
                $tables = DiningTable::query()
                    ->whereIn('id', $tableIds)
                    ->where('branch_id', $locked->branch_id)
                    ->lockForUpdate()
                    ->get();

                if ($tables->count() !== count(array_unique($tableIds))) {
                    throw new HttpException(422, 'One or more tables are unavailable.');
                }

                $start = $locked->reserved_at;
                $end = $locked->endsAt();

                foreach ($tables as $table) {
                    $conflict = Reservation::query()
                        ->where('status', ReservationStatus::CONFIRMED)
                        ->where('id', '!=', $locked->id)
                        ->whereHas('tables', fn ($q) => $q->where('dining_tables.id', $table->id))
                        ->where('reserved_at', '<', $end)
                        ->get()
                        ->first(function (Reservation $other) use ($start): bool {
                            return $other->endsAt()->gt($start);
                        });

                    if ($conflict !== null) {
                        throw new TableConflict;
                    }
                }

                DB::table('reservation_tables')->where('reservation_id', $locked->id)->delete();

                $tenantId = app(TenantContext::class)->id();
                foreach ($tables as $table) {
                    DB::table('reservation_tables')->insert([
                        'tenant_id' => $tenantId,
                        'reservation_id' => $locked->id,
                        'table_id' => $table->id,
                    ]);
                }
            }

            $from = $locked->status;
            $locked->status = ReservationStatus::CONFIRMED;
            $locked->save();

            if ($from !== ReservationStatus::CONFIRMED) {
                ReservationStatusHistory::query()->create([
                    'reservation_id' => $locked->id,
                    'from_status' => $from,
                    'to_status' => ReservationStatus::CONFIRMED,
                    'user_id' => $userId ?? auth()->id(),
                    'created_at' => now(),
                ]);
            }

            return $locked->fresh(['tables']);
        });

        // Email after commit — SMTP failure must not roll back the reservation.
        try {
            $this->queueOutbound->reservationConfirmed($confirmed);
        } catch (\Throwable) {
            // Intentionally ignored.
        }

        return $confirmed;
    }
}
