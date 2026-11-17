<?php

namespace App\Http\Controllers;

use App\Domain\Menu\Actions\UpdateOperatingHours;
use App\Models\Branch;
use App\Models\OperatingHour;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchHoursController extends Controller
{
    public function update(Request $request, string $publicId, UpdateOperatingHours $updateOperatingHours): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('tenant.branches.manage'), 403);

        $branch = Branch::query()->where('public_id', $publicId)->first() ?? abort(404);

        if (! auth()->user()->canAccessBranch($branch)) {
            abort(404);
        }

        $data = $request->validate([
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['nullable', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]);

        $days = [];

        foreach ($data['days'] as $index => $day) {
            $days[] = [
                'day_of_week' => (int) $day['day_of_week'],
                'is_closed' => $request->boolean("days.$index.is_closed"),
                'opens_at' => $day['opens_at'] ?? null,
                'closes_at' => $day['closes_at'] ?? null,
            ];
        }

        try {
            $updateOperatingHours->handle($branch, $days);
        } catch (SubscriptionReadOnly) {
            abort(403);
        } catch (ValidationException $e) {
            throw $e;
        }

        return back()->with('status', __('app.operating_hours_saved'));
    }
}
