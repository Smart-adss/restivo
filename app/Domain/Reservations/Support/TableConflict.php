<?php

namespace App\Domain\Reservations\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TableConflict extends HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'The selected table conflicts with another confirmed reservation.');
    }
}
