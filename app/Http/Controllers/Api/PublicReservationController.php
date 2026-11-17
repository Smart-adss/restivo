<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\ListReservationSlots;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Features\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReservationController extends Controller
{
    public function slots(Request $request, string $slug, string $branchPublicId, ListReservationSlots $listSlots, FeatureGate $features): JsonResponse
    {
        if (! $features->allows('module.reservations')) {
            abort(404);
        }

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $branch = Branch::query()
            ->where('public_id', $branchPublicId)
            ->where('is_active', true)
            ->first() ?? abort(404);

        return response()->json([
            'data' => [
                'duration_minutes' => ListReservationSlots::DURATION_MINUTES,
                'slots' => $listSlots->handle($branch, $data['date']),
            ],
        ]);
    }

    public function store(Request $request, string $slug, CreateReservation $createReservation, FeatureGate $features): JsonResponse
    {
        if (! $features->allows('module.reservations')) {
            abort(404);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'string', 'size:26'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:30'],
            'reserved_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $branch = Branch::query()
            ->where('public_id', $data['branch_id'])
            ->where('is_active', true)
            ->first();

        if ($branch === null) {
            abort(404);
        }

        $reservation = $createReservation->handle([
            'branch_id' => $branch->id,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'guest_count' => $data['guest_count'],
            'reserved_at' => $data['reserved_at'],
            'notes' => $data['notes'] ?? null,
            'source' => 'online',
        ]);

        return response()->json([
            'data' => [
                'id' => $reservation->public_id,
                'status' => $reservation->status,
                'reserved_at' => $reservation->reserved_at->toIso8601String(),
                'guest_count' => $reservation->guest_count,
                'duration_minutes' => $reservation->duration_minutes,
            ],
        ], 201);
    }
}
