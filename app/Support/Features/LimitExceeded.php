<?php

namespace App\Support\Features;

use RuntimeException;

class LimitExceeded extends RuntimeException
{
    public function __construct(public readonly string $featureKey)
    {
        parent::__construct("Feature limit reached for [{$featureKey}].");
    }
}
