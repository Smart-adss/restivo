@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="$ticket->subject" :description="$ticket->user?->name">
        <x-slot:actions>
            <x-ui.button :href="route('platform.support.index')" variant="ghost">{{ __('app.support') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <ul class="space-y-3">
        @foreach ($ticket->messages as $message)
            <li class="ui-panel px-4 py-3 text-sm">
                <p class="font-semibold">{{ $message->user?->name }}</p>
                <p class="mt-1">{{ $message->body }}</p>
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('platform.support.reply', $ticket->public_id) }}" class="ui-panel mt-6 max-w-xl space-y-3 px-4 py-5">
        @csrf
        <x-ui.textarea name="body" required rows="4"></x-ui.textarea>
        <x-ui.button type="submit" variant="primary">{{ __('app.reply') }}</x-ui.button>
    </form>
@endsection
