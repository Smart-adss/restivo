<?php

namespace App\Http\Controllers;

use App\Domain\Menu\Actions\EnsureMenuQrCode;
use App\Domain\Tenancy\Actions\CreateBranch;
use App\Models\Branch;
use App\Models\OperatingHour;
use App\Models\QrCode;
use App\Support\Features\FeatureGate;
use App\Support\Features\LimitExceeded;
use App\Support\Features\SubscriptionReadOnly;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        return view('app.branches.index', [
            'branches' => auth()->user()->visibleBranches(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('app.branches.create');
    }

    public function store(Request $request, CreateBranch $createBranch): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['required', 'string', 'max:160'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('branches', 'code')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        try {
            $branch = $createBranch->handle($data);
        } catch (LimitExceeded) {
            throw ValidationException::withMessages([
                'code' => __('app.limit_reached'),
            ]);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()->route('app.branches.show', $branch->public_id);
    }

    public function show(string $publicId): View
    {
        $branch = Branch::query()->where('public_id', $publicId)->first();
        $user = auth()->user();

        if (! $branch || ! $user->canAccessBranch($branch)) {
            abort(404);
        }

        $hours = OperatingHour::query()
            ->where('branch_id', $branch->id)
            ->orderBy('day_of_week')
            ->get();

        if ($hours->count() < 7) {
            $hours = collect(range(0, 6))->map(function (int $day) use ($hours, $branch) {
                return $hours->firstWhere('day_of_week', $day) ?? new OperatingHour([
                    'branch_id' => $branch->id,
                    'day_of_week' => $day,
                    'opens_at' => '09:00',
                    'closes_at' => '23:00',
                    'is_closed' => false,
                ]);
            });
        }

        $qr = QrCode::query()
            ->where('target_type', 'menu')
            ->where('target_public_id', $branch->public_id)
            ->first();

        return view('app.branches.show', [
            'branch' => $branch,
            'hours' => $hours,
            'qr' => $qr,
        ]);
    }

    public function storeQr(string $publicId, EnsureMenuQrCode $ensureMenuQrCode, FeatureGate $features): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('tenant.branches.manage'), 403);

        if (! $features->allows('module.digital_menu')) {
            abort(404);
        }

        $branch = Branch::query()->where('public_id', $publicId)->first() ?? abort(404);

        if (! auth()->user()->canAccessBranch($branch)) {
            abort(404);
        }

        try {
            $ensureMenuQrCode->handle($branch, force: true);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.qr_generated'));
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->hasPermission('tenant.branches.manage'), 403);
    }
}
