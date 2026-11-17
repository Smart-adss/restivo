<?php

namespace App\Domain\Finance\Actions;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Support\Money\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BuildShiftCashReport
{
    /**
     * @return list<array{
     *   shift_id: int,
     *   public_id: string,
     *   status: string,
     *   opening_float: string,
     *   movements_total: string,
     *   expected_cash: string,
     *   counted_cash: string|null,
     *   difference: string|null,
     *   matches_movements: bool
     * }>
     */
    public function handle(Branch $branch, ?string $from = null, ?string $to = null): array
    {
        $query = CashierShift::query()
            ->where('branch_id', $branch->id)
            ->with('movements')
            ->orderByDesc('opened_at');

        if ($from) {
            $query->where('opened_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where('opened_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if (($from === null) !== ($to === null)) {
            throw ValidationException::withMessages([
                'from' => __('app.report_date_range_required'),
            ]);
        }

        /** @var Collection<int, CashierShift> $shifts */
        $shifts = $query->limit(100)->get();

        $rows = [];

        foreach ($shifts as $shift) {
            $movementsTotal = '0.00';
            foreach ($shift->movements as $movement) {
                $movementsTotal = Money::add($movementsTotal, (string) $movement->signed_amount);
            }
            $expectedFromMovements = Money::add((string) $shift->opening_float, $movementsTotal);
            $storedExpected = $shift->expected_cash !== null ? (string) $shift->expected_cash : $expectedFromMovements;

            $rows[] = [
                'shift_id' => $shift->id,
                'public_id' => $shift->public_id,
                'status' => $shift->status,
                'opening_float' => (string) $shift->opening_float,
                'movements_total' => $movementsTotal,
                'expected_cash' => $storedExpected,
                'counted_cash' => $shift->counted_cash !== null ? (string) $shift->counted_cash : null,
                'difference' => $shift->difference !== null ? (string) $shift->difference : null,
                'matches_movements' => Money::compare($storedExpected, $expectedFromMovements) === 0,
            ];
        }

        return $rows;
    }
}
