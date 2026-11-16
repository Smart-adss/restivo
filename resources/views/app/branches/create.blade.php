@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.create_branch') }}</h1>
    <form method="POST" action="{{ route('app.branches.store') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @foreach (['name_ar', 'name_en', 'code', 'phone', 'city', 'address'] as $name)
            <div>
                <label for="{{ $name }}" class="mb-1 block text-sm">{{ __('app.'.$name) }}</label>
                <input id="{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" @required($name !== 'phone') class="{{ $field }}">
                @error($name)
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        <button type="submit" class="flex min-h-11 items-center justify-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
