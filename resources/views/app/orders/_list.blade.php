@php
    use App\Domain\Ordering\OrderStatusMachine;
@endphp

@if ($orders->isEmpty())
    <p class="rounded-lg border border-line bg-white px-4 py-6 text-sm text-ink/70">{{ __('app.orders_empty') }}</p>
@else
    <ul class="space-y-4">
        @foreach ($orders as $order)
            @php($next = OrderStatusMachine::allowedNext($order->status, $order->type))
            <li class="rounded-lg border border-line bg-white px-4 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">#{{ $order->number }} · {{ __('app.order_status_'.$order->status) }}</p>
                        <p class="mt-1 text-sm text-ink/70">
                            {{ $order->customer_name }} · <span dir="ltr">{{ $order->customer_phone }}</span>
                            · {{ __('app.order_type_'.$order->type) }}
                        </p>
                        <p class="mt-1 text-sm tabular-nums">{{ $order->total }} {{ $order->currency_code }}</p>
                        <ul class="mt-2 text-sm text-ink/80">
                            @foreach ($order->items as $item)
                                <li>{{ app()->getLocale() === 'ar' ? $item->name_ar : $item->name_en }} × {{ $item->quantity }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($next as $status)
                            @if ($status === OrderStatusMachine::CANCELLED)
                                @continue
                            @endif
                            @if (auth()->user()->hasPermission('orders.orders.accept'))
                                <form method="POST" action="{{ route('app.orders.status', $order->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="to" value="{{ $status }}">
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <button type="submit" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.order_status_'.$status) }}</button>
                                </form>
                            @endif
                        @endforeach
                        @if (auth()->user()->hasPermission('orders.orders.cancel') && ! in_array($order->status, [OrderStatusMachine::COMPLETED, OrderStatusMachine::CANCELLED], true))
                            <form method="POST" action="{{ route('app.orders.cancel', $order->public_id) }}" onsubmit="return confirm(@json(__('app.confirm_cancel_order')))">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <button type="submit" class="min-h-11 rounded-lg border border-danger px-3 text-sm text-danger">{{ __('app.cancel_order') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
