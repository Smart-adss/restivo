<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Actions\CreateBranch;
use App\Models\Branch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $branch = $createBranch->handle($data);

        return redirect()->route('app.branches.show', $branch->public_id);
    }

    public function show(string $publicId): View
    {
        $branch = Branch::query()->where('public_id', $publicId)->first();
        $user = auth()->user();

        if (! $branch || ! $user->canAccessBranch($branch)) {
            abort(404);
        }

        return view('app.branches.show', [
            'branch' => $branch,
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->hasPermission('tenant.branches.manage'), 403);
    }
}
