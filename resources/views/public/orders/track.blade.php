<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('app.order_tracking') }} #{{ $order->number }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <main class="mx-auto w-full max-w-xl px-4 py-8">
            <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ __('app.order_tracking') }} #{{ $order->number }}</h1>
            <p class="mt-4 text-lg font-semibold">
                {{ __('app.status') }}:
                <span id="order-status">{{ __('app.order_status_'.$order->status) }}</span>
            </p>
            <p class="mt-2 text-sm text-ink/70">{{ __('app.order_total') }}: <span class="tabular-nums font-semibold">{{ $order->total }} {{ $order->currency_code }}</span></p>
            <ul class="mt-6 space-y-2 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between border-b border-line py-2">
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
    </body>
</html>
