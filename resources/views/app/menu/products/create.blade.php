@extends('layouts.app')

@section('content')
    @php($field = 'ui-field')
    <x-ui.page-header :title="__('app.create_product')" :description="__('app.create_product_hint')">
        <x-slot:actions>
            <x-ui.button :href="route('app.menu.products.index')" variant="ghost">{{ __('app.products') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.panel class="max-w-2xl px-4 py-5">
        <form method="POST" action="{{ route('app.menu.products.store') }}" class="space-y-4">
            @csrf
            @include('app.menu.products._form', ['product' => null, 'categories' => $categories, 'taxRates' => $taxRates, 'prepStations' => $prepStations, 'field' => $field])
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </x-ui.panel>
@endsection
