<?php

namespace App\Domain\Pos\Actions;

use App\Models\CashierShift;
use App\Models\CashRegister;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpenShift
{
    public function __construct(private FeatureGate $features) {}

    public function handle(int $registerId, string $openingFloat, ?int $userId = null): CashierShift
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.pos')) {
            abort(404);
        }

        abort_unless(auth()->user()?->hasPermission('pos.shift.open'), 403);

        if (Money::compare($openingFloat, '0') === -1) {
            throw new HttpException(422, 'Opening float cannot be negative.');
        }

        return DB::transaction(function () use ($registerId, $openingFloat, $userId): CashierShift {
            $register = CashRegister::query()
                ->whereKey($registerId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($register === null) {
                throw new HttpException(404, 'Register not found.');
            }

            $open = CashierShift::query()
                ->where('register_id', $register->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw new HttpException(409, 'This register already has an open shift.');
            }

            return CashierShift::query()->create([
                'branch_id' => $register->branch_id,
                'register_id' => $register->id,
                'user_id' => $userId ?? auth()->id(),
                'status' => 'open',
                'opening_float' => Money::of($openingFloat),
                'opened_at' => now(),
            ]);
        });
    }
}
