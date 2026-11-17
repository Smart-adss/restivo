<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Subscription\AuditLogger;
use App\Domain\Tenancy\Actions\CreateTenant;
use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
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

        $addonQuantities = [];
        if ($subscription !== null) {
            $addonQuantities = DB::table('subscription_addons')
                ->join('addons', 'addons.id', '=', 'subscription_addons.addon_id')
                ->where('subscription_addons.subscription_id', $subscription->id)
                ->pluck('subscription_addons.quantity', 'addons.key')
                ->all();
        }

        app(FeatureGate::class)->flush();

        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'plans' => Plan::query()->where('is_public', true)->orderBy('sort_order')->get(),
            'addons' => Addon::query()->where('is_active', true)->orderBy('key')->get(),
            'addonQuantities' => $addonQuantities,
            'effectiveBranchLimit' => app(FeatureGate::class)->limit('branches.max'),
            'effectiveUserLimit' => app(FeatureGate::class)->limit('users.max'),
            'owner' => $this->owner($tenant),
        ]);
    }

    public function updateOwnerPassword(Request $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage();
        $owner = $this->owner($tenant);

        if ($owner === null) {
            abort(404);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => __('app.owner_password_required'),
            'password.min' => __('app.owner_password_min'),
            'password.confirmed' => __('app.owner_password_confirmed'),
        ]);

        $owner->password = $data['password'];
        $owner->save();
        DB::table('sessions')->where('user_id', $owner->id)->delete();

        app(TenantContext::class)->set($tenant->id);
        $audit->log('tenant.owner_password_reset', $owner, null, ['email' => $owner->email]);

        return redirect()
            ->route('platform.tenants.show', $tenant->public_id)
            ->with('status', __('app.owner_password_saved'));
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

    private function owner(Tenant $tenant): ?User
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', function ($query): void {
                $query->select('user_roles.user_id')
                    ->from('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('roles.key', 'business_owner');
            })
            ->first();
    }
}
