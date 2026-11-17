@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.create_category') }}</h1>
    <form method="POST" action="{{ route('app.menu.categories.store') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @include('app.menu.categories._form', ['category' => null, 'parents' => $parents, 'field' => $field])
        <button type="submit" class="flex min-h-11 items-center justify-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
