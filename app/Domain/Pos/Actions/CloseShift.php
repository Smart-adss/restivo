<?php

namespace App\Domain\Pos\Actions;

use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CloseShift
{
    public function __construct(private FeatureGate $features) {}

    public function handle(CashierShift $shift, string $countedCash): CashierShift
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('pos.shift.close'), 403);

        if (Money::compare($countedCash, '0') === -1) {
            throw new HttpException(422, 'Counted cash cannot be negative.');
        }

        return DB::transaction(function () use ($shift, $countedCash): CashierShift {
            $locked = CashierShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new HttpException(409, 'The shift is already closed.');
            }

            $movements = CashMovement::query()
                ->where('shift_id', $locked->id)
                ->sum('signed_amount');

            $expected = Money::add((string) $locked->opening_float, Money::of((string) $movements));
            $counted = Money::of($countedCash);
            $difference = Money::sub($counted, $expected);

            $locked->status = 'closed';
            $locked->expected_cash = $expected;
            $locked->counted_cash = $counted;
            $locked->difference = $difference;
            $locked->closed_at = now();
            $locked->save();

            return $locked->fresh(['movements']);
        });
    }
}
