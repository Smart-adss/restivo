<?php

namespace App\Domain\Pos\Actions;

use App\Domain\Ordering\Actions\CreateOrder;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Features\FeatureGate;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreatePosSale
{
    public function __construct(
        private FeatureGate $features,
        private CreateOrder $createOrder,
    ) {}

    /**
     * @param  array{
     *   shift_id: int,
     *   idempotency_key: string,
     *   type?: string,
     *   notes?: ?string,
     *   items: list<array{product_id: int, quantity: mixed, notes?: ?string, option_ids?: list<int>}>,
     *   payments: list<array{method: string, amount: string|float|int}>
     * }  $data
     * @return array{order: Order, payments: list<Payment>}
     */
    public function handle(array $data): array
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.pos')) {
            abort(404);
        }

        abort_unless(auth()->user()?->hasPermission('pos.sale.create'), 403);

        $existing = Order::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->with(['payments'])
            ->first();

        if ($existing !== null) {
            return [
                'order' => $existing,
                'payments' => $existing->payments->all(),
            ];
        }

        return DB::transaction(function () use ($data): array {
            $shift = CashierShift::query()
                ->whereKey($data['shift_id'])
                ->lockForUpdate()
                ->first();

            if ($shift === null || $shift->status !== 'open') {
                throw new HttpException(409, 'An open shift is required.');
            }

            if ((int) $shift->user_id !== (int) auth()->id() && ! auth()->user()?->hasPermission('orders.orders.accept')) {
                throw new HttpException(403, 'This shift belongs to another cashier.');
            }

            $settings = DB::table('tenant_settings')
                ->where('tenant_id', $shift->tenant_id)
                ->where('key', 'pos.require_shift')
                ->value('value');
            $requireShift = $settings === null ? true : (bool) json_decode((string) $settings, true);

            if ($requireShift && $shift->status !== 'open') {
                throw new HttpException(409, 'An open shift is required.');
            }

            $order = $this->createOrder->handle([
                'branch_id' => $shift->branch_id,
                'type' => $data['type'] ?? 'dine_in',
                'source' => 'pos',
                'idempotency_key' => $data['idempotency_key'],
                'notes' => $data['notes'] ?? null,
                'items' => $data['items'],
                'cashier_shift_id' => $shift->id,
                'status' => 'completed',
            ]);

            $paymentsInput = $data['payments'] ?? [];
            $paid = '0.00';

            foreach ($paymentsInput as $payment) {
                $paid = Money::add($paid, Money::of($payment['amount']));
            }

            if (Money::compare($paid, (string) $order->total) !== 0) {
                throw ValidationException::withMessages([
                    'total' => __('app.pos_payment_total_mismatch'),
                    'expected_total' => (string) $order->total,
                ]);
            }

            $payments = [];
            $cashTotal = '0.00';

            foreach ($paymentsInput as $payment) {
                $method = $payment['method'];
                if (! in_array($method, ['cash', 'card', 'wallet', 'instapay'], true)) {
                    throw ValidationException::withMessages([
                        'payments' => __('app.pos_payment_method_invalid'),
                    ]);
                }

                $amount = Money::of($payment['amount']);
                if (Money::compare($amount, '0') !== 1) {
                    throw ValidationException::withMessages([
                        'payments' => __('app.pos_payment_amount_invalid'),
                    ]);
                }

                $row = Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => $method,
                    'status' => 'paid',
                    'amount' => $amount,
                    'currency_code' => $order->currency_code,
                    'paid_at' => now(),
                ]);
                $payments[] = $row;

                if ($method === 'cash') {
                    $cashTotal = Money::add($cashTotal, $amount);
                }
            }

            if (Money::compare($cashTotal, '0') === 1) {
                CashMovement::query()->create([
                    'shift_id' => $shift->id,
                    'kind' => 'sale',
                    'amount' => $cashTotal,
                    'signed_amount' => $cashTotal,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'created_at' => now(),
                ]);
            }

            return [
                'order' => $order->fresh(['items.options', 'payments']),
                'payments' => $payments,
            ];
        });
    }
}
