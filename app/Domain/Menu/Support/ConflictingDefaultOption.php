<?php

namespace App\Domain\Menu\Support;

use RuntimeException;

class ConflictingDefaultOption extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A single-selection group already has a default option.');
    }
}
