@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.support') }}</h1>
    <ul class="mt-6 divide-y divide-line rounded-lg border border-line bg-white">
        @foreach ($tickets as $ticket)
            <li>
                <a href="{{ route('platform.support.show', $ticket->public_id) }}" class="block px-4 py-3">
                    <span class="font-semibold">{{ $ticket->subject }}</span>
                    <span class="mt-1 block text-sm text-ink/70">{{ $ticket->user?->name }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
