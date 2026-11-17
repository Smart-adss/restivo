<?php

namespace App\Domain\Pos\Actions;

use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateRefund
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{
     *   payment_id: int,
     *   amount: string|float|int,
     *   reason: string,
     *   idempotency_key: string
     * }  $data
     */
    public function handle(Order $order, array $data): Refund
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('pos.refund.create'), 403);

        if (! $this->features->allows('module.pos')) {
            abort(404);
        }

        $existing = Refund::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if ($order->status !== 'completed') {
            throw new HttpException(422, 'Only completed orders can be refunded.');
        }

        return DB::transaction(function () use ($order, $data): Refund {
            $existing = Refund::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $payment = Payment::query()
                ->whereKey($data['payment_id'])
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($payment === null || $payment->status === 'failed') {
                throw ValidationException::withMessages([
                    'payment_id' => __('app.refund_payment_unavailable'),
                ]);
            }

            $amount = Money::of($data['amount']);

            if (Money::compare($amount, '0') !== 1) {
                throw ValidationException::withMessages([
                    'amount' => __('app.refund_amount_invalid'),
                ]);
            }

            $already = Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', 'completed')
                ->lockForUpdate()
                ->get()
                ->reduce(fn (string $carry, Refund $row): string => Money::add($carry, (string) $row->amount), '0.00');

            $remaining = Money::sub((string) $payment->amount, $already);

            if (Money::compare($amount, $remaining) === 1) {
                throw ValidationException::withMessages([
                    'amount' => __('app.refund_exceeds_remaining'),
                    'remaining' => $remaining,
                ]);
            }

            $shift = null;

            if ($order->cashier_shift_id !== null) {
                $shift = CashierShift::query()
                    ->whereKey($order->cashier_shift_id)
                    ->lockForUpdate()
                    ->first();
            }

            if ($shift !== null && $shift->status !== 'open') {
                $user = auth()->user();
                $isManager = $user?->hasPermission('orders.orders.accept')
                    || $user?->hasPermission('tenant.settings.manage');

                if (! $isManager) {
                    throw new HttpException(403, 'A closed shift refund requires a manager.');
                }
            }

            $refund = Refund::query()->create([
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'amount' => $amount,
                'reason' => $data['reason'],
                'status' => 'completed',
                'idempotency_key' => $data['idempotency_key'],
                'processed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            if (Money::compare($amount, $remaining) === 0) {
                $payment->update(['status' => 'refunded']);
            }

            if ($payment->method === 'cash' && $shift !== null && $shift->status === 'open') {
                CashMovement::query()->create([
                    'shift_id' => $shift->id,
                    'kind' => 'refund',
                    'amount' => $amount,
                    'signed_amount' => Money::sub('0', $amount),
                    'reference_type' => Refund::class,
                    'reference_id' => $refund->id,
                    'note' => $data['reason'],
                    'created_at' => now(),
                ]);
            }

            return $refund;
        });
    }
}
