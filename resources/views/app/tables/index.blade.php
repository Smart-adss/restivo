@extends('layouts.ops')

@section('ops-title', __('app.floor'))
@section('ops-context', $branches->firstWhere('id', (int) $branch_id)?->name() ?? '')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex items-end gap-2">
            <div>
                <x-ui.label for="floor-branch">{{ __('app.branch') }}</x-ui.label>
                <x-ui.select id="floor-branch" name="branch_id" onchange="this.form.submit()">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branch_id === (int) $branch->id)>{{ $branch->name() }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        </form>
    </div>

    @if (auth()->user()->hasPermission('tables.layout.manage'))
        <div class="grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('app.tables.areas.store') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <x-ui.section-title :title="__('app.add_dining_area')" />
                <input type="hidden" name="branch_id" value="{{ $branch_id }}">
                <x-ui.field name="name_ar" required maxlength="120" placeholder="{{ __('app.name_ar') }}" />
                <x-ui.field name="name_en" required maxlength="120" placeholder="{{ __('app.name_en') }}" />
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>

            <form method="POST" action="{{ route('app.tables.tables.store') }}" class="ui-panel space-y-3 p-4">
                @csrf
                <x-ui.section-title :title="__('app.add_dining_table')" />
                <input type="hidden" name="branch_id" value="{{ $branch_id }}">
                <x-ui.select name="area_id">
                    <option value="">{{ __('app.no_area') }}</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ app()->getLocale() === 'ar' ? $area->name_ar : $area->name_en }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field name="name" required maxlength="80" placeholder="{{ __('app.table_name') }}" value="{{ old('name') }}" />
                @error('name')
                    <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
                <x-ui.field name="seats" type="number" min="1" max="30" value="2" required class="tabular-nums" />
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>
        </div>
    @endif

    <div class="mt-8 space-y-8">
        @forelse ($areas as $area)
            <section>
                <x-ui.section-title :title="app()->getLocale() === 'ar' ? $area->name_ar : $area->name_en" />
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($area->tables as $table)
                        @include('app.tables.partials.table-card', ['table' => $table, 'sessions' => $sessions, 'qrs' => $qrs, 'orphan_tables' => $orphan_tables, 'areas' => $areas])
                    @endforeach
                </ul>
            </section>
        @empty
            @if ($orphan_tables->isEmpty())
                <x-ui.empty-state :title="__('app.floor_empty_title')" :body="__('app.floor_empty_hint')" />
            @endif
        @endforelse

        @if ($orphan_tables->isNotEmpty())
            <section>
                <x-ui.section-title :title="__('app.no_area')" />
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($orphan_tables as $table)
                        @include('app.tables.partials.table-card', ['table' => $table, 'sessions' => $sessions, 'qrs' => $qrs, 'orphan_tables' => $orphan_tables, 'areas' => $areas])
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
