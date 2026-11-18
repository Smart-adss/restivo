@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.categories')" :description="__('app.categories_catalog_hint')">
        <x-slot:actions>
            <x-ui.button :href="route('app.menu.categories.create')" variant="primary">
                {{ __('app.create_category') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($categories->isEmpty())
        <x-ui.empty-state :title="__('app.no_categories')" :body="__('app.categories_empty_hint')">
            <x-slot:action>
                <x-ui.button :href="route('app.menu.categories.create')" variant="primary">
                    {{ __('app.create_category') }}
                </x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <ul class="space-y-3">
            @foreach ($categories as $category)
                <li class="ui-panel px-4 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $category->name() }}</p>
                            <p class="mt-1 text-sm text-ink/65">{{ __('app.sort_order') }}: <span class="tabular-nums">{{ $category->sort_order }}</span></p>
                        </div>
                        <x-ui.button :href="route('app.menu.categories.edit', $category->public_id)" variant="ghost">{{ __('app.edit') }}</x-ui.button>
                    </div>
                    @if ($category->children->isNotEmpty())
                        <ul class="mt-3 space-y-2 border-s border-line ps-4">
                            @foreach ($category->children as $child)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="text-sm">{{ $child->name() }}</span>
                                    <x-ui.button :href="route('app.menu.categories.edit', $child->public_id)" variant="ghost">{{ __('app.edit') }}</x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
