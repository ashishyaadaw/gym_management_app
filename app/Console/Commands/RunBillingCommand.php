<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

class RunBillingCommand extends Command
{
    protected $signature = 'billing:run';
    protected $description = 'Process automated membership renewals and expirations (run daily via scheduler)';

    public function handle(BillingService $billing)
    {
        $result = $billing->runDailyBilling();

        $this->info("Billing run complete. Renewed: {$result['renewed']}, Expired: {$result['expired']}.");

        return self::SUCCESS;
    }
}
