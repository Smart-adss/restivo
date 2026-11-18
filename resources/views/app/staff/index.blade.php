@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.staff')">
        <x-slot:actions>
            <x-ui.button :href="route('app.staff.create')" variant="primary">{{ __('app.create_staff') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ui-panel overflow-hidden">
        <ul>
            @forelse ($staff as $member)
                <li class="flex items-center justify-between gap-4 border-b border-line px-4 py-3 last:border-b-0">
                    <div>
                        <p class="font-semibold">{{ $member->name }}</p>
                        <p class="text-sm text-ink/70">{{ $member->email }}</p>
                    </div>
                    <p class="text-sm text-ink/70">
                        @foreach ($roleNames->get($member->id, []) as $role)
                            {{ app()->getLocale() === 'ar' ? $role->name_ar : $role->name_en }}
                        @endforeach
                    </p>
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.empty_title') }}</li>
            @endforelse
        </ul>
    </div>
@endsection
