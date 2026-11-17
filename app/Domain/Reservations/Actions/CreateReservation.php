<?php

namespace App\Domain\Reservations\Actions;

use App\Domain\Ordering\Actions\UpsertCustomerByPhone;
use App\Domain\Reservations\ReservationStatus;
use App\Models\Branch;
use App\Models\Reservation;
use App\Models\ReservationStatusHistory;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReservation
{
    public function __construct(
        private FeatureGate $features,
        private ListReservationSlots $slots,
        private UpsertCustomerByPhone $upsertCustomer,
    ) {}

    /**
     * @param  array{
     *   branch_id: int,
     *   customer_name: string,
     *   customer_phone: string,
     *   guest_count: int,
     *   reserved_at: string,
     *   notes?: ?string,
     *   source?: string,
     *   duration_minutes?: int
     * }  $data
     */
    public function handle(array $data): Reservation
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.reservations')) {
            abort(404);
        }

        $branch = Branch::query()->find($data['branch_id']);

        if ($branch === null || ! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => __('app.branch_unavailable'),
            ]);
        }

        $guestCount = (int) $data['guest_count'];

        if ($guestCount < 1 || $guestCount > 30) {
            throw ValidationException::withMessages([
                'guest_count' => __('app.guest_count_invalid'),
            ]);
        }

        $duration = (int) ($data['duration_minutes'] ?? ListReservationSlots::DURATION_MINUTES);
        $source = $data['source'] ?? 'online';
        $timezone = DB::table('tenants')->where('id', app(TenantContext::class)->id())->value('timezone') ?: 'Africa/Cairo';
        $reservedAt = Carbon::parse($data['reserved_at'], $timezone);

        if ($source === 'online' && $reservedAt->lt(Carbon::now($timezone)->addHour())) {
            throw ValidationException::withMessages([
                'reserved_at' => __('app.reservation_too_soon'),
            ]);
        }

        $date = $reservedAt->toDateString();
        $available = $this->slots->handle($branch, $date, enforceLeadTime: $source === 'online');
        $normalized = collect($available)
            ->map(fn (string $slot) => Carbon::parse($slot, $timezone)->format('Y-m-d H:i'))
            ->all();

        if (! in_array($reservedAt->format('Y-m-d H:i'), $normalized, true)) {
            throw ValidationException::withMessages([
                'reserved_at' => __('app.reservation_slot_unavailable'),
            ]);
        }

        return DB::transaction(function () use ($data, $branch, $guestCount, $duration, $source, $reservedAt): Reservation {
            $customer = $this->upsertCustomer->handle(
                $data['customer_phone'],
                $data['customer_name'],
            );

            $autoConfirm = $this->branchAutoConfirm($branch->id);
            $status = match ($source) {
                'online' => $autoConfirm ? ReservationStatus::CONFIRMED : ReservationStatus::PENDING,
                default => ReservationStatus::CONFIRMED,
            };

            $reservation = Reservation::query()->create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'guest_count' => $guestCount,
                'reserved_at' => $reservedAt,
                'duration_minutes' => $duration,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'source' => $source,
            ]);

            ReservationStatusHistory::query()->create([
                'reservation_id' => $reservation->id,
                'from_status' => null,
                'to_status' => $status,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);

            return $reservation->fresh(['tables']);
        });
    }

    private function branchAutoConfirm(int $branchId): bool
    {
        $raw = DB::table('branch_settings')
            ->where('branch_id', $branchId)
            ->where('key', 'reservations.auto_confirm')
            ->value('value');

        if ($raw === null) {
            return false;
        }

        return (bool) json_decode((string) $raw, true);
    }
}
