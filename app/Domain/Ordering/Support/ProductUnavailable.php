<?php

namespace App\Domain\Ordering\Support;

use RuntimeException;

class ProductUnavailable extends RuntimeException
{
    public function __construct(string $message = 'A product in the order is unavailable.')
    {
        parent::__construct($message);
    }
}
