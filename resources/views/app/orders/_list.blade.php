@php
    use App\Domain\Ordering\OrderStatusMachine;
@endphp

@if ($orders->isEmpty())
    <x-ui.empty-state :title="__('app.orders_empty')" />
@else
    <ul class="space-y-3">
        @foreach ($orders as $order)
            @php($next = OrderStatusMachine::allowedNext($order->status, $order->type))
            <li class="ui-panel px-4 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">#{{ $order->number }} · {{ __('app.order_status_'.$order->status) }}</p>
                        <p class="mt-1 text-sm text-ink/70">
                            {{ $order->customer_name }} · <span dir="ltr">{{ $order->customer_phone }}</span>
                            · {{ __('app.order_type_'.$order->type) }}
                        </p>
                        <p class="mt-1 text-sm tabular-nums font-semibold">{{ $order->total }} {{ $order->currency_code }}</p>
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
                                    <x-ui.button type="submit" variant="secondary">{{ __('app.order_status_'.$status) }}</x-ui.button>
                                </form>
                            @endif
                        @endforeach
                        @if (auth()->user()->hasPermission('orders.orders.cancel') && ! in_array($order->status, [OrderStatusMachine::COMPLETED, OrderStatusMachine::CANCELLED], true))
                            <form method="POST" action="{{ route('app.orders.cancel', $order->public_id) }}" onsubmit="return confirm(@json(__('app.confirm_cancel_order')))">
                                @csrf
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <x-ui.button type="submit" variant="danger">{{ __('app.cancel_order') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
