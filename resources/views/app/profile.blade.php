@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.profile') }}</h1>
    <form method="POST" action="{{ route('app.profile.update') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="mb-1 block text-sm">{{ __('app.name') }}</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="{{ $field }}">
        </div>
        <div>
            <label for="phone" class="mb-1 block text-sm">{{ __('app.phone') }}</label>
            <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="{{ $field }}">
        </div>
        <div>
            <label for="locale" class="mb-1 block text-sm">{{ __('app.locale') }}</label>
            <select id="locale" name="locale" class="{{ $field }}">
                <option value="ar" @selected(old('locale', $user->locale) === 'ar')>العربية</option>
                <option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
            </select>
        </div>
        <button type="submit" class="flex min-h-11 items-center justify-center rounded-md bg-brand px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
