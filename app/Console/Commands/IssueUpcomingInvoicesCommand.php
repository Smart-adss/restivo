<?php

namespace App\Console\Commands;

use App\Domain\Subscription\IssueUpcomingInvoices;
use Illuminate\Console\Command;

class IssueUpcomingInvoicesCommand extends Command
{
    protected $signature = 'billing:issue-invoices';

    protected $description = 'Issue the next invoice once when a period is near its end';

    public function handle(IssueUpcomingInvoices $action): int
    {
        $issued = $action->handle();
        $this->info("Issued {$issued} invoices.");

        return self::SUCCESS;
    }
}
