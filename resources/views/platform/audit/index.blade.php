@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.audit') }}</h1>
    <ul class="mt-6 space-y-3">
        @foreach ($logs as $log)
            <li class="rounded-lg border border-line bg-white px-4 py-3 text-sm">
                <p class="font-semibold">{{ $log->action }}</p>
                <p class="mt-2 text-ink/70">{{ json_encode($log->before, JSON_UNESCAPED_UNICODE) }}</p>
                <p class="text-ink/70">{{ json_encode($log->after, JSON_UNESCAPED_UNICODE) }}</p>
            </li>
        @endforeach
    </ul>
@endsection
