@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="$plan->name()" :description="$plan->code">
        <x-slot:actions>
            <x-ui.button :href="route('platform.plans.index')" variant="ghost">{{ __('app.plans') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('platform.plans.update', $plan->code) }}" class="ui-panel max-w-xl space-y-4 px-4 py-5">
        @csrf
        @method('PUT')
        <div>
            <x-ui.label for="name_ar">{{ __('app.name_ar') }}</x-ui.label>
            <x-ui.field id="name_ar" name="name_ar" value="{{ old('name_ar', $plan->name_ar) }}" />
        </div>
        <div>
            <x-ui.label for="name_en">{{ __('app.name_en') }}</x-ui.label>
            <x-ui.field id="name_en" name="name_en" value="{{ old('name_en', $plan->name_en) }}" />
        </div>
        <div>
            <x-ui.label for="description">{{ __('app.description') }}</x-ui.label>
            <x-ui.textarea id="description" name="description">{{ old('description', $plan->description) }}</x-ui.textarea>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <x-ui.label for="monthly_amount">{{ __('app.monthly') }}</x-ui.label>
                <x-ui.field id="monthly_amount" name="monthly_amount" value="{{ old('monthly_amount', $plan->priceFor('monthly')) }}" class="tabular-nums" />
            </div>
            <div>
                <x-ui.label for="yearly_amount">{{ __('app.yearly') }}</x-ui.label>
                <x-ui.field id="yearly_amount" name="yearly_amount" value="{{ old('yearly_amount', $plan->priceFor('yearly')) }}" class="tabular-nums" />
            </div>
        </div>
        <input type="hidden" name="is_active" value="{{ $plan->is_active ? 1 : 0 }}">
        <input type="hidden" name="is_public" value="{{ $plan->is_public ? 1 : 0 }}">
        <input type="hidden" name="trial_days" value="{{ $plan->trial_days }}">
        @foreach ($catalog as $key => $definition)
            @php($current = $plan->features->firstWhere('key', $key)?->pivot->value)
            <div>
                <x-ui.label for="feature-{{ $key }}">{{ $definition['name_ar'] }} <span class="text-ink/50">{{ $key }}</span></x-ui.label>
                <x-ui.field id="feature-{{ $key }}" name="features[{{ $key }}]" value="{{ old('features.'.$key, $current) }}" />
            </div>
        @endforeach
        <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
    </form>
@endsection
