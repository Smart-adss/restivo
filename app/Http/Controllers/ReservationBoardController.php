<?php

namespace App\Http\Controllers;

use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\ConfirmReservation;
use App\Domain\Reservations\Actions\MarkReservationNoShow;
use App\Domain\Reservations\Support\TableConflict;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\WhatsApp;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationBoardController extends Controller
{
    public function index(Request $request, FeatureGate $features): View
    {
        abort_unless(auth()->user()->hasPermission('reservations.reservations.manage'), 403);

        if (! $features->allows('module.reservations')) {
            abort(404);
        }

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $tenant = Tenant::query()->findOrFail(auth()->user()->tenant_id);
        $date = (string) $request->query('date', Carbon::now($tenant->timezone)->toDateString());
        $dayStart = Carbon::parse($date, $tenant->timezone)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        $reservations = Reservation::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('reserved_at', [$dayStart, $dayEnd])
            ->with(['tables'])
            ->orderBy('reserved_at')
            ->get();

        $tables = DiningTable::query()
            ->where('branch_id', $branch->id)
            ->orderBy('name')
            ->get();

        $waText = __('app.reservation_wa_text', [
            'branch' => $branch->name(),
            'date' => $date,
        ]);

        return view('app.reservations.index', [
            'branches' => $branches,
            'branch' => $branch,
            'date' => $date,
            'reservations' => $reservations,
            'tables' => $tables,
            'whatsapp_url' => WhatsApp::meUrl($tenant->phone, $waText),
        ]);
    }

    public function confirm(Request $request, string $publicId, ConfirmReservation $confirm): RedirectResponse
    {
        $data = $request->validate([
            'table_ids' => ['nullable', 'array'],
            'table_ids.*' => ['integer'],
            'branch_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reservation = Reservation::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $confirm->handle($reservation, $data['table_ids'] ?? []);
        } catch (TableConflict $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        } catch (HttpException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.reservations.index', array_filter([
                'branch_id' => $data['branch_id'] ?? $reservation->branch_id,
                'date' => $data['date'] ?? $reservation->reserved_at->toDateString(),
            ]))
            ->with('status', __('app.reservation_confirmed'));
    }

    public function cancel(Request $request, string $publicId, CancelReservation $cancel): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reservation = Reservation::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $cancel->handle($reservation);
        } catch (HttpException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.reservations.index', array_filter([
                'branch_id' => $data['branch_id'] ?? $reservation->branch_id,
                'date' => $data['date'] ?? $reservation->reserved_at->toDateString(),
            ]))
            ->with('status', __('app.reservation_cancelled'));
    }

    public function noShow(Request $request, string $publicId, MarkReservationNoShow $markNoShow): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $reservation = Reservation::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $markNoShow->handle($reservation);
        } catch (HttpException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('app.reservations.index', array_filter([
                'branch_id' => $data['branch_id'] ?? $reservation->branch_id,
                'date' => $data['date'] ?? $reservation->reserved_at->toDateString(),
            ]))
            ->with('status', __('app.reservation_marked_no_show'));
    }
}
