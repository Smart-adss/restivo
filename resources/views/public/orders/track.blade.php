@extends('layouts.public')

@section('title', __('app.order_tracking').' #'.$order->number)

@section('body')
    <main class="mx-auto w-full max-w-xl px-4 py-8">
        <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('app.order_tracking') }} #{{ $order->number }}</h1>

        <div class="ui-panel mt-6 px-4 py-5">
            <p class="text-sm text-ink/65">{{ __('app.status') }}</p>
            <p class="mt-1 text-xl font-semibold" id="order-status">{{ __('app.order_status_'.$order->status) }}</p>
            <p class="mt-3 text-sm text-ink/70">
                {{ __('app.order_total') }}:
                <span class="tabular-nums font-semibold text-ink">{{ $order->total }} {{ $order->currency_code }}</span>
            </p>
        </div>

        <ul class="ui-panel mt-4 overflow-hidden">
            @foreach ($order->items as $item)
                <li class="flex justify-between border-b border-line px-4 py-3 text-sm last:border-b-0">
                    <span>{{ app()->getLocale() === 'ar' ? $item->name_ar : $item->name_en }} × {{ $item->quantity }}</span>
                    <span class="tabular-nums">{{ $item->line_total }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-6 text-xs text-ink/50">{{ __('app.tracking_poll_hint') }}</p>
    </main>
    <script>
        (() => {
            const url = @json($poll_url);
            const labels = @json($status_labels);
            const statusEl = document.getElementById('order-status');

            async function poll() {
                try {
                    const { data } = await window.axios.get(url, { headers: { Accept: 'application/json' } });
                    const status = data.data.status;
                    statusEl.textContent = labels[status] || status;
                } catch (e) {
                    // keep last known status
                }
            }

            setInterval(poll, 15000);
        })();
    </script>
@endsection
