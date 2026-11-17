<?php

namespace App\Http\Controllers;

use App\Domain\Tenancy\Actions\SaveTaxRate;
use App\Models\TaxRate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxRateController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasPermission('tenant.settings.manage'), 403);

        return view('app.taxes.index', [
            'rates' => TaxRate::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SaveTaxRate $save): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('tenant.settings.manage'), 403);

        try {
            $save->create($this->data($request));
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()->route('app.taxes.index')->with('status', __('app.tax_rate_saved'));
    }

    public function update(Request $request, string $publicId, SaveTaxRate $save): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('tenant.settings.manage'), 403);

        $rate = TaxRate::query()->where('public_id', $publicId)->firstOrFail();

        try {
            $save->update($rate, $this->data($request));
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return redirect()->route('app.taxes.index')->with('status', __('app.tax_rate_saved'));
    }

    /**
     * @return array{name: string, rate_percent: mixed, is_inclusive: bool, is_default: bool, is_active: bool}
     */
    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_inclusive' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => __('app.name'),
            'rate_percent' => __('app.tax_rate_percent'),
        ]);

        return [
            'name' => $data['name'],
            'rate_percent' => $data['rate_percent'],
            'is_inclusive' => $request->boolean('is_inclusive'),
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
