@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="$tenant->displayName()" :description="$tenant->slug">
        <x-slot:actions>
            <span class="text-sm font-semibold">{{ $tenant->status === 'active' ? __('app.status_active') : __('app.status_suspended') }}</span>
            <x-ui.button :href="route('platform.tenants.index')" variant="ghost">{{ __('app.tenants') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (auth()->user()->hasPermission('platform.tenants.manage') && $owner)
        <section class="ui-panel max-w-xl px-4 py-5">
            <x-ui.section-title :title="__('app.tenant_profile')" :description="$tenant->slug" />
            <form method="POST" action="{{ route('platform.tenants.profile.update', $tenant->public_id) }}" class="mt-4 space-y-3">
                @csrf
                @foreach ([
                    'legal_name' => $tenant->legal_name,
                    'display_name_ar' => $tenant->display_name_ar,
                    'display_name_en' => $tenant->display_name_en,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'owner_name' => $owner->name,
                    'owner_email' => $owner->email,
                ] as $name => $value)
                    <div>
                        <x-ui.label for="{{ $name }}">{{ __('app.'.$name) }}</x-ui.label>
                        <x-ui.field id="{{ $name }}" name="{{ $name }}" type="{{ str_contains($name, 'email') ? 'email' : 'text' }}" value="{{ old($name, $value) }}" required />
                        @error($name)
                            <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>
        </section>

        <section class="ui-panel mt-6 max-w-xl px-4 py-5">
            <x-ui.section-title :title="__('app.owner_password')" :description="$owner->email.' — '.__('app.owner_password_hint')" />
            <form method="POST" action="{{ route('platform.tenants.owner-password.update', $tenant->public_id) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <x-ui.label for="password">{{ __('app.password') }}</x-ui.label>
                    <x-ui.field id="password" name="password" type="password" required autocomplete="new-password" />
                    @error('password')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <x-ui.label for="password_confirmation">{{ __('app.password_confirmation') }}</x-ui.label>
                    <x-ui.field id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
                </div>
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>
        </section>
    @endif

    @if (session('temporary_password'))
        <x-ui.alert variant="warning" class="mt-6 max-w-xl">
            <span class="block font-semibold">{{ __('app.temporary_password') }}</span>
            <span class="mt-2 block font-mono text-lg" dir="ltr">{{ session('temporary_password') }}</span>
            <span class="mt-2 block text-sm">{{ session('owner_email') }} — {{ __('app.temporary_password_hint') }}</span>
        </x-ui.alert>
    @endif

    @if ($subscription)
        <section class="ui-panel mt-6 max-w-xl px-4 py-5 text-sm">
            <p class="font-semibold">{{ $subscription->plan->name() }}</p>
            <p class="mt-1">{{ __('app.subscription_status_'.$subscription->status) }}</p>
            <p class="mt-1 tabular-nums text-ink/70">{{ $subscription->current_period_end->timezone('Africa/Cairo')->toDateString() }}</p>
            <dl class="mt-4 space-y-2 border-t border-line pt-3">
                <div class="flex justify-between gap-4">
                    <dt>{{ __('app.branches') }}</dt>
                    <dd class="tabular-nums">{{ $usage['branch_count'] }} / {{ $usage['branch_limit'] ?? __('app.unlimited') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt>{{ __('app.staff') }}</dt>
                    <dd class="tabular-nums">{{ $usage['user_count'] }} / {{ $usage['user_limit'] ?? __('app.unlimited') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt>{{ __('app.orders') }}</dt>
                    <dd class="tabular-nums">{{ $usage['order_count'] }} / {{ $usage['order_limit'] ?? __('app.unlimited') }}</dd>
                </div>
            </dl>
            @foreach ($subscription->invoices as $invoice)
                <p class="mt-2">{{ $invoice->status }} — <span class="tabular-nums">{{ $invoice->total }} {{ $invoice->currency_code }}</span></p>
            @endforeach
        </section>
        @if (auth()->user()->hasPermission('platform.billing.record_payment'))
            <form method="POST" action="{{ route('platform.tenants.payments.store', $tenant->public_id) }}" class="mt-4 max-w-xl space-y-3">
                @csrf
                <x-ui.select name="invoice_id">
                    @foreach ($subscription->invoices->where('status', 'open') as $invoice)
                        <option value="{{ $invoice->id }}">{{ $invoice->total }} — {{ $invoice->public_id }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field name="provider_ref" placeholder="{{ __('app.provider_ref') }}" />
                @error('provider_ref')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                <x-ui.button type="submit" variant="primary">{{ __('app.record_payment') }}</x-ui.button>
            </form>
        @endif
        @if (auth()->user()->hasPermission('platform.plans.manage'))
            <form method="POST" action="{{ route('platform.tenants.plan.update', $tenant->public_id) }}" class="mt-4 flex max-w-xl gap-2">
                @csrf
                <x-ui.select name="plan_code">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->code }}" @selected($subscription->plan->code === $plan->code)>{{ $plan->name() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.button type="submit" variant="secondary">{{ __('app.change_plan') }}</x-ui.button>
            </form>
            @error('plan_code')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror

            <section class="ui-panel mt-6 max-w-xl px-4 py-5 text-sm">
                <x-ui.section-title :title="__('app.subscription_addons')" />
                <p class="mt-1 text-ink/70">
                    {{ __('app.effective_branch_limit') }}: {{ $effectiveBranchLimit ?? __('app.unlimited') }}
                    · {{ __('app.effective_user_limit') }}: {{ $effectiveUserLimit ?? __('app.unlimited') }}
                </p>
                @foreach ($addons as $addon)
                    <form method="POST" action="{{ route('platform.tenants.addons.update', $tenant->public_id) }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="addon_key" value="{{ $addon->key }}">
                        <div class="min-w-[10rem] flex-1">
                            <x-ui.label>{{ $addon->name() }}</x-ui.label>
                            <x-ui.field type="number" name="quantity" min="0" max="100" value="{{ $addonQuantities[$addon->key] ?? 0 }}" class="tabular-nums" />
                        </div>
                        <x-ui.button type="submit" variant="secondary">{{ __('app.save') }}</x-ui.button>
                    </form>
                @endforeach
                @error('quantity')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                @error('addon_key')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </section>
        @endif
    @endif

    @if (auth()->user()->hasPermission('platform.tenants.manage'))
        <form method="POST" action="{{ route($tenant->status === 'active' ? 'platform.tenants.suspend' : 'platform.tenants.activate', $tenant->public_id) }}" class="mt-6">
            @csrf
            <x-ui.button type="submit" :variant="$tenant->status === 'active' ? 'danger' : 'primary'">
                {{ $tenant->status === 'active' ? __('app.suspend') : __('app.activate') }}
            </x-ui.button>
        </form>
    @endif
@endsection
