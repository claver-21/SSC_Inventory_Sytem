<?php

namespace App\Console\Commands;

use App\Services\OverdueMonitoringService;
use Illuminate\Console\Command;

class CheckOverdueBorrowingsCommand extends Command
{
    protected $signature = 'inventory:check-overdue';

    protected $description = 'Detect overdue and upcoming borrowing return deadlines and send notifications.';

    public function handle(OverdueMonitoringService $service): int
    {
        $summary = $service->run();

        $this->info("Overdue processed: {$summary['overdue_processed']}, Upcoming notified: {$summary['upcoming_notified']}");

        return self::SUCCESS;
    }
}
