<?php

namespace App\Domain\Inventory\Support;

use App\Models\UnitConversion;
use RuntimeException;

class UnitConverter
{
    /**
     * Convert a quantity from one unit to another using direct or chained conversions.
     */
    public function convert(string $quantity, int $fromUnitId, int $toUnitId): string
    {
        if ($fromUnitId === $toUnitId) {
            return number_format((float) $quantity, 3, '.', '');
        }

        $edges = UnitConversion::query()
            ->get(['from_unit_id', 'to_unit_id', 'factor'])
            ->groupBy('from_unit_id');

        $queue = [[$fromUnitId, '1']];
        $visited = [$fromUnitId => true];

        while ($queue !== []) {
            [$current, $factorSoFar] = array_shift($queue);

            foreach ($edges->get($current, collect()) as $edge) {
                $next = (int) $edge->to_unit_id;
                $nextFactor = bcmul($factorSoFar, (string) $edge->factor, 8);

                if ($next === $toUnitId) {
                    return number_format((float) bcmul($quantity, $nextFactor, 8), 3, '.', '');
                }

                if (! isset($visited[$next])) {
                    $visited[$next] = true;
                    $queue[] = [$next, $nextFactor];
                }
            }
        }

        throw new RuntimeException("No conversion path from unit {$fromUnitId} to {$toUnitId}.");
    }
}
