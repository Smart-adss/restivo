@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.pos') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('app.pos_placeholder') }}</p>
    <form method="POST" action="{{ route('app.pos.store') }}" class="mt-6">
        @csrf
        <button class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.pos_record') }}</button>
    </form>
@endsection
