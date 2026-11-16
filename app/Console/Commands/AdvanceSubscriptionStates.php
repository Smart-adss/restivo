<?php

namespace App\Console\Commands;

use App\Domain\Subscription\AdvanceSubscriptions;
use Illuminate\Console\Command;

class AdvanceSubscriptionStates extends Command
{
    protected $signature = 'billing:advance-states';

    protected $description = 'Move subscription states from their dates';

    public function handle(AdvanceSubscriptions $action): int
    {
        $changed = $action->handle();
        $this->info("Updated {$changed} subscriptions.");

        return self::SUCCESS;
    }
}
