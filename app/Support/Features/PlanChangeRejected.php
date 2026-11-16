<?php

namespace App\Support\Features;

use RuntimeException;

class PlanChangeRejected extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The current usage does not fit the selected plan.');
    }
}
