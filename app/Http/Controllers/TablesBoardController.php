<?php

namespace App\Http\Controllers;

use App\Domain\Tables\Actions\CloseTableSession;
use App\Domain\Tables\Actions\CreateDiningArea;
use App\Domain\Tables\Actions\CreateDiningTable;
use App\Domain\Tables\Actions\EnsureTableQrCode;
use App\Domain\Tables\Actions\OpenTableSession;
use App\Domain\Tables\Actions\SplitBill;
use App\Domain\Tables\Actions\TransferTableSession;
use App\Models\DiningArea;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\TableSession;
use App\Support\Features\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TablesBoardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            auth()->user()->hasPermission('tables.sessions.manage')
            || auth()->user()->hasPermission('tables.layout.manage'),
            403,
        );

        $branches = auth()->user()->visibleBranches();
        $branchId = (int) $request->query('branch_id', $branches->first()?->id ?? 0);
        abort_unless($branches->contains(fn ($branch) => (int) $branch->id === $branchId), 404);

        $areas = DiningArea::query()
            ->where('branch_id', $branchId)
            ->with(['tables' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        $orphanTables = DiningTable::query()
            ->where('branch_id', $branchId)
            ->whereNull('area_id')
            ->orderBy('name')
            ->get();

        $sessions = TableSession::query()
            ->where('status', 'open')
            ->whereHas('table', fn ($q) => $q->where('branch_id', $branchId))
            ->with(['table', 'orders'])
            ->get()
            ->keyBy('table_id');

        $qrs = QrCode::query()
            ->where('target_type', 'table')
            ->whereIn('target_public_id', $orphanTables->pluck('public_id')->merge(
                $areas->flatMap->tables->pluck('public_id')
            ))
            ->get()
            ->keyBy('target_public_id');

        return view('app.tables.index', [
            'branches' => $branches,
            'branch_id' => $branchId,
            'areas' => $areas,
            'orphan_tables' => $orphanTables,
            'sessions' => $sessions,
            'qrs' => $qrs,
        ]);
    }

    public function storeArea(Request $request, CreateDiningArea $createDiningArea): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $createDiningArea->handle($data);

        return redirect()
            ->route('app.tables.index', ['branch_id' => $data['branch_id']])
            ->with('status', __('app.dining_area_created'));
    }

    public function storeTable(Request $request, CreateDiningTable $createDiningTable): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'seats' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $createDiningTable->handle($data);

        return redirect()
            ->route('app.tables.index', ['branch_id' => $data['branch_id']])
            ->with('status', __('app.dining_table_created'));
    }

    public function openSession(Request $request, string $publicId, OpenTableSession $openTableSession): RedirectResponse|JsonResponse
    {
        $table = DiningTable::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($table->branch), 404);

        $data = $request->validate([
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        try {
            $session = $openTableSession->handle($table, (int) ($data['guest_count'] ?? 1));
        } catch (HttpException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'id' => $session->public_id,
                    'status' => $session->status,
                    'table_id' => $table->public_id,
                ],
            ], 201);
        }

        return redirect()
            ->route('app.tables.index', ['branch_id' => $table->branch_id])
            ->with('status', __('app.table_session_opened'));
    }

    public function closeSession(string $publicId, CloseTableSession $closeTableSession): RedirectResponse
    {
        $session = TableSession::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($session->table->branch), 404);

        $closeTableSession->handle($session);

        return redirect()
            ->route('app.tables.index', ['branch_id' => $session->table->branch_id])
            ->with('status', __('app.table_session_closed'));
    }

    public function transfer(Request $request, string $publicId, TransferTableSession $transferTableSession): RedirectResponse|JsonResponse
    {
        $session = TableSession::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($session->table->branch), 404);

        $data = $request->validate([
            'to_table_id' => ['required', 'string', 'size:26'],
        ]);

        $to = DiningTable::query()->where('public_id', $data['to_table_id'])->first();

        if ($to === null) {
            throw ValidationException::withMessages([
                'to_table_id' => __('app.dining_table_unavailable'),
            ]);
        }

        try {
            $transferTableSession->handle($session, $to->id);
        } catch (HttpException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('app.table_session_transferred')]);
        }

        return redirect()
            ->route('app.tables.index', ['branch_id' => $to->branch_id])
            ->with('status', __('app.table_session_transferred'));
    }

    public function split(Request $request, string $publicId, SplitBill $splitBill): JsonResponse
    {
        $order = Order::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($order->branch), 404);

        $data = $request->validate([
            'mode' => ['required', 'in:equal,items,amount'],
            'count' => ['nullable', 'integer', 'min:2', 'max:20'],
            'parts' => ['nullable', 'array', 'min:2'],
            'parts.*.label' => ['nullable', 'string', 'max:120'],
            'parts.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'parts.*.order_item_ids' => ['nullable', 'array'],
            'parts.*.order_item_ids.*' => ['integer'],
        ]);

        try {
            $split = $splitBill->handle($order, $data);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => __('app.bill_split_total_mismatch'),
                'error' => [
                    'fields' => $exception->errors(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $split->public_id,
                'mode' => $split->mode,
                'parts' => $split->parts->map(static fn ($part): array => [
                    'label' => $part->label,
                    'amount' => (string) $part->amount,
                ])->all(),
            ],
        ], 201);
    }

    public function storeQr(string $publicId, EnsureTableQrCode $ensureTableQrCode, FeatureGate $features): RedirectResponse
    {
        if (! $features->allows('module.online_ordering')) {
            abort(404);
        }

        $table = DiningTable::query()->where('public_id', $publicId)->first() ?? abort(404);
        abort_unless(auth()->user()->canAccessBranch($table->branch), 404);

        $ensureTableQrCode->handle($table, force: true);

        return redirect()
            ->route('app.tables.index', ['branch_id' => $table->branch_id])
            ->with('status', __('app.table_qr_generated'));
    }
}
