<?php

namespace App\Http\Controllers;

use App\Domain\Identity\RoleCatalog;
use App\Domain\Subscription\AuditLogger;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use App\Support\Features\SubscriptionReadOnly;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        $tenantId = app(TenantContext::class)->id();
        $staff = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $roleNames = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->whereIn('user_roles.user_id', $staff->pluck('id'))
            ->get(['user_roles.user_id', 'roles.name_ar', 'roles.name_en'])
            ->groupBy('user_id');

        return view('app.staff.index', [
            'staff' => $staff,
            'roleNames' => $roleNames,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('app.staff.create', [
            'branches' => auth()->user()->visibleBranches(),
            'roles' => Role::query()->whereIn('key', RoleCatalog::assignableStaffKeys())->orderBy('name_ar')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
            'role_key' => ['required', Rule::in(RoleCatalog::assignableStaffKeys())],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer'],
        ]);

        $branches = Branch::query()->whereIn('id', $data['branch_ids'])->get();

        if ($branches->count() !== count(array_unique($data['branch_ids']))) {
            throw ValidationException::withMessages([
                'branch_ids' => __('app.branch_unavailable'),
            ]);
        }

        $role = Role::query()->where('key', $data['role_key'])->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role_key' => __('app.role_unavailable'),
            ]);
        }

        $activeUsers = User::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('status', 'active')
            ->count();

        try {
            app(FeatureGate::class)->assertUnderCeiling('users.max', $activeUsers);
        } catch (LimitExceeded) {
            throw ValidationException::withMessages([
                'email' => __('app.limit_reached'),
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        DB::transaction(function () use ($data, $branches, $role): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'locale' => 'ar',
                'status' => 'active',
                'is_platform_user' => false,
            ]);
            $user->forceFill(['tenant_id' => app(TenantContext::class)->id()])->save();

            foreach ($branches as $branch) {
                DB::table('branch_user')->insert([
                    'user_id' => $user->id,
                    'branch_id' => $branch->id,
                    'is_primary' => $branch->is($branches->first()),
                ]);
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'branch_id' => $branch->id,
                ]);
            }
        });

        app(AuditLogger::class)->log('staff.role_assigned', auth()->user(), null, [
            'role_key' => $data['role_key'],
            'email' => $data['email'],
        ]);

        return redirect()->route('app.staff.index')->with('status', __('app.staff_saved'));
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->hasPermission('tenant.users.manage'), 403);
    }
}
