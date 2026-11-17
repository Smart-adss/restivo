@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.menu_theme') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('app.menu_theme_hint') }}</p>
    <form method="POST" action="{{ route('app.menu.theme.update') }}" class="mt-6 grid gap-3 md:grid-cols-2">
        @csrf
        @method('PUT')
        @foreach ($presets as $key => $preset)
            <label class="flex cursor-pointer gap-3 rounded-lg border border-line bg-white px-4 py-4">
                <input type="radio" name="preset_key" value="{{ $key }}" @checked($active === $key) class="mt-1">
                <span class="min-w-0">
                    <span class="block font-semibold">{{ app()->getLocale() === 'ar' ? $preset['name_ar'] : $preset['name_en'] }}</span>
                    <span class="mt-2 flex gap-2">
                        @foreach (['brand', 'canvas', 'accent'] as $swatch)
                            <span class="inline-block h-6 w-6 rounded-full border border-line" style="background: {{ $preset['tokens'][$swatch] }}"></span>
                        @endforeach
                    </span>
                </span>
            </label>
        @endforeach
        @error('preset_key')
            <p class="text-sm text-danger md:col-span-2">{{ $message }}</p>
        @enderror
        <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink md:col-span-2">{{ __('app.save') }}</button>
    </form>
@endsection
