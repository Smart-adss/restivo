@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.orders')" :description="__('app.orders_board_hint')">
        <x-slot:actions>
            <form method="GET" action="{{ route('app.orders.index') }}" class="flex items-end gap-2">
                <div>
                    <x-ui.label for="orders-branch">{{ __('app.branch') }}</x-ui.label>
                    <x-ui.select id="orders-branch" name="branch_id" onchange="this.form.submit()">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @error('status')
        <x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>
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
