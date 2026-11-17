<?php

namespace App\Domain\Pos\Actions;

use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecordCashMovement
{
    public function __construct(private FeatureGate $features) {}

    public function handle(CashierShift $shift, string $kind, string $amount, ?string $note = null): CashMovement
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('pos.shift.open'), 403);

        if (! in_array($kind, ['pay_in', 'pay_out'], true)) {
            throw new HttpException(422, 'Unsupported movement kind.');
        }

        if (Money::compare($amount, '0') !== 1) {
            throw new HttpException(422, 'Amount must be positive.');
        }

        return DB::transaction(function () use ($shift, $kind, $amount, $note): CashMovement {
            $locked = CashierShift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new HttpException(409, 'The shift is closed.');
            }

            $signed = $kind === 'pay_in' ? Money::of($amount) : Money::sub('0', Money::of($amount));

            return CashMovement::query()->create([
                'shift_id' => $locked->id,
                'kind' => $kind,
                'amount' => Money::of($amount),
                'signed_amount' => $signed,
                'note' => $note,
                'created_at' => now(),
            ]);
        });
    }
}
