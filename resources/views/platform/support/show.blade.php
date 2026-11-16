@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ $ticket->subject }}</h1>
    <p class="mt-1 text-sm text-ink/70">{{ $ticket->user?->name }}</p>
    <ul class="mt-6 space-y-3">
        @foreach ($ticket->messages as $message)
            <li class="rounded-lg border border-line bg-white px-4 py-3 text-sm">
                <p class="font-semibold">{{ $message->user?->name }}</p>
                <p class="mt-1">{{ $message->body }}</p>
            </li>
        @endforeach
    </ul>
    <form method="POST" action="{{ route('platform.support.reply', $ticket->public_id) }}" class="mt-6 max-w-xl space-y-3">
        @csrf
        <textarea name="body" required class="w-full rounded-md border border-line bg-white px-3 py-3"></textarea>
        <button class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.reply') }}</button>
    </form>
@endsection
