<?php

namespace App\Domain\Menu\Support;

use RuntimeException;

class InvalidProductImage extends RuntimeException
{
    public function __construct(string $message = 'The uploaded file is not a valid image.')
    {
        parent::__construct($message);
    }
}
