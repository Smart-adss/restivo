<?php

namespace App\Support\Features;

use RuntimeException;

class PlanChangeRejected extends RuntimeException
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(public readonly array $messages = [])
    {
        parent::__construct(
            $messages === []
                ? 'The current usage does not fit the selected plan.'
                : implode(' ', $messages)
        );
    }
}
