<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Support\Features\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DiningTableController extends Controller
{
    public function store(Request $request, FeatureGate $features): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('reservations.reservations.manage'), 403);

        if (! $features->allows('module.reservations')) {
            abort(404);
        }

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:80'],
            'seats' => ['required', 'integer', 'min:1', 'max:30'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $branchIds = auth()->user()->visibleBranches()->pluck('id');
        abort_unless($branchIds->contains((int) $data['branch_id']), 403);

        if (DiningTable::query()->where('branch_id', $data['branch_id'])->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => __('app.dining_table_name_taken'),
            ]);
        }

        DiningTable::query()->create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'seats' => $data['seats'],
            'status' => 'available',
        ]);

        return redirect()
            ->route('app.reservations.index', array_filter([
                'branch_id' => $data['branch_id'],
                'date' => $data['date'] ?? null,
            ]))
            ->with('status', __('app.dining_table_created'));
    }
}
