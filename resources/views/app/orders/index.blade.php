@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('app.orders') }}</h1>
            <p class="mt-1 text-sm text-ink/70">{{ __('app.orders_board_hint') }}</p>
        </div>
        <form method="GET" action="{{ route('app.orders.index') }}" class="flex items-end gap-2">
            <label class="text-sm">{{ __('app.branch') }}
                <select name="branch_id" class="mt-1 min-h-11 rounded-lg border border-line px-3" onchange="this.form.submit()">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-lg border border-s-4 border-accent bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif
    @error('status')
        <p class="mb-4 rounded-lg border border-s-4 border-danger bg-white px-4 py-3 text-sm text-danger">{{ $message }}</p>
    @enderror

    <div id="orders-board"
         data-poll-url="{{ route('app.orders.poll', ['branch_id' => $branch->id]) }}"
         data-interval="15000">
        @include('app.orders._list', ['orders' => $orders, 'branch' => $branch])
    </div>

    <script>
        (() => {
            const root = document.getElementById('orders-board');
            const url = root.dataset.pollUrl;
            const interval = Number(root.dataset.interval || 15000);

            async function poll() {
                try {
                    const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) return;
                    root.innerHTML = await response.text();
                } catch (e) {}
            }

            setInterval(poll, interval);
        })();
    </script>
@endsection
