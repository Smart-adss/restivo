@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.create_tenant') }}</h1>
    <form method="POST" action="{{ route('platform.tenants.store') }}" class="mt-6 grid max-w-3xl gap-4 md:grid-cols-2">
        @csrf
        @foreach ([
            'legal_name', 'display_name_ar', 'display_name_en', 'slug', 'email', 'phone',
            'owner_name', 'owner_email', 'branch_name_ar', 'branch_name_en', 'branch_code', 'city', 'address',
        ] as $name)
            <div class="{{ in_array($name, ['address', 'legal_name'], true) ? 'md:col-span-2' : '' }}">
                <label for="{{ $name }}" class="mb-1 block text-sm">{{ __('app.'.$name) }}</label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ str_contains($name, 'email') ? 'email' : 'text' }}" value="{{ old($name) }}" required class="{{ $field }}">
                @error($name)
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        <div class="md:col-span-2">
            <button type="submit" class="flex min-h-11 items-center justify-center rounded-md bg-brand px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </div>
    </form>
@endsection
