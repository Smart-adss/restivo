@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.edit_category')">
        <x-slot:actions>
            <x-ui.button :href="route('app.menu.categories.index')" variant="ghost">{{ __('app.categories') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.panel class="max-w-xl px-4 py-5">
        <form method="POST" action="{{ route('app.menu.categories.update', $category->public_id) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @include('app.menu.categories._form', ['category' => $category, 'parents' => $parents])
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </x-ui.panel>
@endsection