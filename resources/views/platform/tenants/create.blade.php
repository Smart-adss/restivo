@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.create_tenant')">
        <x-slot:actions>
            <x-ui.button :href="route('platform.tenants.index')" variant="ghost">{{ __('app.tenants') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('platform.tenants.store') }}" class="ui-panel grid max-w-3xl gap-4 px-4 py-5 md:grid-cols-2">
        @csrf
        @foreach ([
            'legal_name', 'display_name_ar', 'display_name_en', 'slug', 'email', 'phone',
            'owner_name', 'owner_email', 'branch_name_ar', 'branch_name_en', 'branch_code', 'city', 'address',
        ] as $name)
            <div class="{{ in_array($name, ['address', 'legal_name'], true) ? 'md:col-span-2' : '' }}">
                <x-ui.label for="{{ $name }}">{{ __('app.'.$name) }}</x-ui.label>
                <x-ui.field id="{{ $name }}" name="{{ $name }}" type="{{ str_contains($name, 'email') ? 'email' : 'text' }}" value="{{ old($name) }}" required />
                @error($name)
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        <div>
            <x-ui.label for="plan_code">{{ __('app.plan') }}</x-ui.label>
            <x-ui.select id="plan_code" name="plan_code">
                @foreach ($plans as $plan)
                    <option value="{{ $plan->code }}" @selected(old('plan_code', 'starter') === $plan->code)>{{ $plan->name() }}</option>
                @endforeach
            </x-ui.select>
        </div>
        <div>
            <x-ui.label for="interval">{{ __('app.interval') }}</x-ui.label>
            <x-ui.select id="interval" name="interval">
                <option value="monthly">{{ __('app.monthly') }}</option>
                <option value="yearly">{{ __('app.yearly') }}</option>
            </x-ui.select>
        </div>
        <label class="flex min-h-11 items-center gap-2 text-sm md:col-span-2">
            <input type="checkbox" name="start_trial" value="1">
            <span>{{ __('app.start_trial') }}</span>
        </label>
        <div class="md:col-span-2">
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </div>
    </form>
@endsection
