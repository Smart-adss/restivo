<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Subscription\AuditLogger;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        return view('platform.tenants.index', [
            'tenants' => Tenant::query()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('platform.tenants.create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, CreateTenant $createTenant): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:190'],
            'display_name_ar' => ['required', 'string', 'max:190'],
            'display_name_en' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', 'unique:tenants,slug', Rule::notIn(['app', 'login', 'platform', 'locale', 'up', 'api'])],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:20'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'branch_name_ar' => ['required', 'string', 'max:160'],
            'branch_name_en' => ['required', 'string', 'max:160'],
            'branch_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'plan_code' => ['nullable', 'string', Rule::exists('plans', 'code')],
            'interval' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'start_trial' => ['nullable', 'boolean'],
        ]);

        $created = $createTenant->handle($data);

        return redirect()
            ->route('platform.tenants.show', $created['tenant']->public_id)
            ->with('temporary_password', $created['password'])
            ->with('owner_email', $created['owner']->email);
    }

    public function show(Tenant $tenant): View
    {
        app(TenantContext::class)->set($tenant->id);
        $subscription = $tenant->subscription?->load('plan', 'invoices');

        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'plans' => Plan::query()->where('is_public', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function suspend(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage();
        app(TenantContext::class)->set($tenant->id);
        $before = ['status' => $tenant->status];
        $tenant->update(['status' => 'suspended']);
        $audit->log('tenant.suspended', $tenant, $before, ['status' => 'suspended']);

        return redirect()->route('platform.tenants.show', $tenant->public_id)->with('status', __('app.tenant_suspended_notice'));
    }

    public function activate(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage();
        app(TenantContext::class)->set($tenant->id);
        $before = ['status' => $tenant->status];
        $tenant->update(['status' => 'active']);
        $audit->log('tenant.activated', $tenant, $before, ['status' => 'active']);

        return redirect()->route('platform.tenants.show', $tenant->public_id)->with('status', __('app.tenant_activated_notice'));
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->hasPermission('platform.tenants.manage'), 403);
    }
}
