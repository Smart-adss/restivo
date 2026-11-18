@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.support')" :description="__('app.platform_support_hint')" />

    @if ($tickets->isEmpty())
        <x-ui.empty-state :title="__('app.support_empty')" />
    @else
        <ul class="ui-panel divide-y divide-line overflow-hidden !p-0">
            @foreach ($tickets as $ticket)
                <li>
                    <a href="{{ route('platform.support.show', $ticket->public_id) }}" class="block px-4 py-3 transition hover:bg-canvas/60">
                        <span class="font-semibold">{{ $ticket->subject }}</span>
                        <span class="mt-1 block text-sm text-ink/70">{{ $ticket->user?->name }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
