@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.create_product') }}</h1>
    <form method="POST" action="{{ route('app.menu.products.store') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @include('app.menu.products._form', ['product' => null, 'categories' => $categories, 'taxRates' => $taxRates, 'field' => $field])
        <button type="submit" class="flex min-h-11 items-center justify-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
