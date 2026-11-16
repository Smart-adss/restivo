<?php

namespace App\Support\Features;

use RuntimeException;

class DuplicatePayment extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This payment was already recorded.');
    }
}
