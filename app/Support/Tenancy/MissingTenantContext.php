<?php

namespace App\Support\Tenancy;

use RuntimeException;

class MissingTenantContext extends RuntimeException
{
    public function __construct(string $model)
    {
        parent::__construct("Tenant context is required for [{$model}].");
    }
}
