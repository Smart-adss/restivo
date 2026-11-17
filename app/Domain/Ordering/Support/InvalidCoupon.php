<?php

namespace App\Domain\Ordering\Support;

use RuntimeException;

class InvalidCoupon extends RuntimeException
{
    public function __construct(string $message = 'The coupon cannot be applied.')
    {
        parent::__construct($message);
    }
}
