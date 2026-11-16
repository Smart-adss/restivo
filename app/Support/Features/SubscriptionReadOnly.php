<?php

namespace App\Support\Features;

use RuntimeException;

class SubscriptionReadOnly extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The subscription is read only.');
    }
}
