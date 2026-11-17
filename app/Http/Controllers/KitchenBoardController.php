<?php

namespace App\Http\Controllers;

use App\Domain\Kitchen\Actions\CreatePrepStation;
use App\Domain\Kitchen\Actions\ReadyKitchenTicket;
use App\Domain\Kitchen\Actions\StartKitchenTicket;
use App\Models\KitchenTicket;
use App\Models\PrepStation;
use App\Support\Features\FeatureGate;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KitchenBoardController extends Controller
{
    public function index(Request $request, FeatureGate $features): View
    {
        abort_unless($features->allows('module.kitchen'), 404);
        abort_unless(
            auth()->user()->hasPermission('kitchen.tickets.view')
            || auth()->user()->hasPermission('tenant.settings.manage'),
            403,
        );

        $branches = auth()->user()->visibleBranches()->where('is_active', true)->values();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        abort_if($branch === null, 404);

        $stations = PrepStation::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $stationId = $request->query('station_id');
        $selectedStation = $stationId
            ? $stations->firstWhere('public_id', $stationId)
            : $stations->first();

        $lateMinutes = $this->lateMinutes((int) $branch->id);
        $pollSeconds = $this->pollSeconds();

        $tickets = collect();

        if ($selectedStation !== null) {
            $tickets = $this->openTicketsQuery($branch->id, $selectedStation->id)->get();
        }

        return view('app.kitchen.index', [
            'branches' => $branches,
            'branch' => $branch,
            'stations' => $stations,
            'selected_station' => $selectedStation,
            'tickets' => $tickets,
            'late_minutes' => $lateMinutes,
            'poll_seconds' => $pollSeconds,
            'poll_url' => route('app.kitchen.poll', array_filter([
                'branch_id' => $branch->id,
                'station_id' => $selectedStation?->public_id,
            ])),
        ]);
    }

    public function poll(Request $request, FeatureGate $features): JsonResponse
    {
        abort_unless($features->allows('module.kitchen'), 404);
        abort_unless(auth()->user()->hasPermission('kitchen.tickets.view'), 403);

        $branches = auth()->user()->visibleBranches()->pluck('id');
        $branchId = (int) $request->query('branch_id');
        abort_unless($branches->contains($branchId), 404);

        $station = null;
        if ($request->filled('station_id')) {
            $station = PrepStation::query()
                ->where('public_id', $request->query('station_id'))
                ->where('branch_id', $branchId)
                ->first() ?? abort(404);
        }

        $updatedAfter = $request->query('updated_after');
        $query = $this->openTicketsQuery($branchId, $station?->id);

        if (is_string($updatedAfter) && $updatedAfter !== '') {
            try {
                $query->where('updated_at', '>', Carbon::parse($updatedAfter));
            } catch (\Throwable) {
                // ignore invalid cursor
            }
        }

        $lateMinutes = $this->lateMinutes($branchId);
        $tickets = $query->get()->map(fn (KitchenTicket $ticket): array => $this->ticketPayload($ticket, $lateMinutes));

        return response()->json([
            'data' => [
                'tickets' => $tickets,
                'server_time' => now()->toIso8601String(),
                'late_minutes' => $lateMinutes,
            ],
        ]);
    }

    public function storeStation(Request $request, CreatePrepStation $createPrepStation): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:kitchen,bar,dessert,bakery'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $station = $createPrepStation->handle($data);

        return redirect()
            ->route('app.kitchen.index', [
                'branch_id' => $station->branch_id,
                'station_id' => $station->public_id,
            ])
            ->with('status', __('app.prep_station_created'));
    }

    public function start(string $publicId, StartKitchenTicket $startKitchenTicket): RedirectResponse|JsonResponse
    {
        $ticket = KitchenTicket::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($ticket->branch), 404);

        try {
            $ticket = $startKitchenTicket->handle($ticket);
        } catch (HttpException $exception) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
            }

            throw $exception;
        }

        if (request()->expectsJson()) {
            return response()->json(['data' => $this->ticketPayload($ticket, $this->lateMinutes((int) $ticket->branch_id))]);
        }

        return back()->with('status', __('app.kitchen_ticket_started'));
    }

    public function ready(string $publicId, ReadyKitchenTicket $readyKitchenTicket): RedirectResponse|JsonResponse
    {
        $ticket = KitchenTicket::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($ticket->branch), 404);

        try {
            $ticket = $readyKitchenTicket->handle($ticket);
        } catch (HttpException $exception) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
            }

            throw $exception;
        }

        if (request()->expectsJson()) {
            return response()->json(['data' => $this->ticketPayload($ticket, $this->lateMinutes((int) $ticket->branch_id))]);
        }

        return back()->with('status', __('app.kitchen_ticket_ready'));
    }

    private function openTicketsQuery(int $branchId, ?int $stationId)
    {
        $query = KitchenTicket::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', ['queued', 'in_progress'])
            ->with(['items', 'station', 'order:id,public_id,number,type,notes,placed_at'])
            ->orderBy('created_at');

        if ($stationId !== null) {
            $query->where('station_id', $stationId);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(KitchenTicket $ticket, int $lateMinutes): array
    {
        $ageMinutes = (int) $ticket->created_at->diffInMinutes(now());
        $isLate = $ageMinutes >= $lateMinutes;

        return [
            'id' => $ticket->public_id,
            'status' => $ticket->status,
            'order_number' => $ticket->order?->number,
            'order_type' => $ticket->order?->type,
            'notes' => $ticket->order?->notes,
            'station' => $ticket->station?->name(),
            'created_at' => optional($ticket->created_at)?->toIso8601String(),
            'updated_at' => optional($ticket->updated_at)?->toIso8601String(),
            'age_minutes' => $ageMinutes,
            'is_late' => $isLate,
            'late_label' => $isLate ? __('app.kitchen_ticket_late') : null,
            'items' => $ticket->items->map(static fn ($item): array => [
                'name_ar' => $item->name_ar,
                'name_en' => $item->name_en,
                'quantity' => (string) $item->quantity,
                'notes' => $item->notes,
            ])->all(),
        ];
    }

    private function lateMinutes(int $branchId): int
    {
        $raw = DB::table('branch_settings')
            ->where('branch_id', $branchId)
            ->where('key', 'kitchen.late_minutes')
            ->value('value');

        if ($raw === null) {
            return 15;
        }

        $decoded = json_decode((string) $raw, true);

        return max(1, (int) $decoded);
    }

    private function pollSeconds(): int
    {
        $raw = DB::table('system_settings')->where('key', 'kitchen.poll_seconds')->value('value');

        if ($raw === null) {
            return 8;
        }

        $decoded = json_decode((string) $raw, true);

        return max(3, (int) $decoded);
    }
}
