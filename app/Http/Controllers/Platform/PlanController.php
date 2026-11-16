<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Subscription\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Support\Features\FeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $this->authorizePlans();

        return view('platform.plans.index', [
            'plans' => Plan::query()->with('prices')->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Plan $plan): View
    {
        $this->authorizePlans();

        return view('platform.plans.edit', [
            'plan' => $plan->load('features', 'prices'),
            'catalog' => FeatureCatalog::all(),
        ]);
    }

    public function update(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $this->authorizePlans();

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:160'],
            'name_en' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'monthly_amount' => ['required', 'numeric', 'min:0'],
            'yearly_amount' => ['required', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:64'],
        ]);

        $before = ['name_ar' => $plan->name_ar, 'code' => $plan->code];
        $plan->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'],
            'is_public' => $data['is_public'],
            'trial_days' => $data['trial_days'],
        ]);

        $plan->prices()->updateOrCreate(
            ['interval' => 'monthly', 'currency_code' => 'EGP'],
            ['amount' => $data['monthly_amount'], 'is_active' => true],
        );
        $plan->prices()->updateOrCreate(
            ['interval' => 'yearly', 'currency_code' => 'EGP'],
            ['amount' => $data['yearly_amount'], 'is_active' => true],
        );

        foreach ($data['features'] ?? [] as $key => $value) {
            if (! array_key_exists($key, FeatureCatalog::all())) {
                continue;
            }

            $featureId = Feature::query()->where('key', $key)->value('id');

            if ($featureId === null) {
                continue;
            }

            $stored = $value === '' ? null : $value;

            if ($plan->features()->where('features.id', $featureId)->exists()) {
                $plan->features()->updateExistingPivot($featureId, ['value' => $stored]);
            } else {
                $plan->features()->attach($featureId, ['value' => $stored]);
            }
        }

        $audit->log('plan.updated', $plan, $before, [
            'name_ar' => $plan->name_ar,
            'code' => $plan->code,
        ]);

        return redirect()->route('platform.plans.edit', $plan)->with('status', __('app.plan_saved'));
    }

    private function authorizePlans(): void
    {
        abort_unless(auth()->user()->hasPermission('platform.plans.manage'), 403);
    }
}
