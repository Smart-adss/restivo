<?php

namespace App\Support\Tenancy;

use RuntimeException;

class TenantMismatch extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The record tenant does not match the current tenant.');
    }
}
