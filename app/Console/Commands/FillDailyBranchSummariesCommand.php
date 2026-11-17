<?php

namespace App\Console\Commands;

use App\Domain\Finance\Actions\FillDailyBranchSummaries;
use Illuminate\Console\Command;

class FillDailyBranchSummariesCommand extends Command
{
    protected $signature = 'reports:fill-daily-summaries';

    protected $description = 'Rebuild daily branch summaries for yesterday and today';

    public function handle(FillDailyBranchSummaries $action): int
    {
        $written = $action->handle();
        $this->info("Updated {$written} daily branch summary rows.");

        return self::SUCCESS;
    }
}
