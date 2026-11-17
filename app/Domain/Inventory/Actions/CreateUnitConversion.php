<?php

namespace App\Domain\Inventory\Actions;

use App\Models\Unit;
use App\Models\UnitConversion;
use App\Support\Features\FeatureGate;
use Illuminate\Validation\ValidationException;

class CreateUnitConversion
{
    public function __construct(private FeatureGate $features) {}

    /**
     * @param  array{from_unit_id: int, to_unit_id: int, factor: string|float|int}  $data
     */
    public function handle(array $data): UnitConversion
    {
        $this->features->assertWritable();
        abort_unless(auth()->user()?->hasPermission('inventory.items.manage'), 403);

        if (! $this->features->allows('module.inventory')) {
            abort(404);
        }

        $fromId = (int) $data['from_unit_id'];
        $toId = (int) $data['to_unit_id'];
        $factor = (string) $data['factor'];

        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_unit_id' => __('app.unit_conversion_same_unit'),
            ]);
        }

        if (! is_numeric($factor) || bccomp($factor, '0', 6) !== 1) {
            throw ValidationException::withMessages([
                'factor' => __('app.unit_conversion_factor_invalid'),
            ]);
        }

        $from = Unit::query()->find($fromId);
        $to = Unit::query()->find($toId);

        if ($from === null || $to === null) {
            throw ValidationException::withMessages([
                'from_unit_id' => __('app.unit_unavailable'),
            ]);
        }

        if ($this->wouldCreateCycle($fromId, $toId)) {
            throw ValidationException::withMessages([
                'to_unit_id' => __('app.unit_conversion_circular'),
            ]);
        }

        return UnitConversion::query()->create([
            'from_unit_id' => $fromId,
            'to_unit_id' => $toId,
            'factor' => number_format((float) $factor, 6, '.', ''),
        ]);
    }

    private function wouldCreateCycle(int $fromId, int $toId): bool
    {
        $edges = UnitConversion::query()
            ->get(['from_unit_id', 'to_unit_id'])
            ->groupBy('from_unit_id')
            ->map(fn ($rows) => $rows->pluck('to_unit_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $edges[$fromId] = array_values(array_unique(array_merge($edges[$fromId] ?? [], [$toId])));

        $visited = [];
        $stack = [$toId];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current === $fromId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($edges[$current] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
