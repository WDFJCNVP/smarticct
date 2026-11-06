<?php

namespace App\Console\Commands;

use App\Services\RentalExpiryService;
use Illuminate\Console\Command;

class ExpireRentalRequests extends Command
{
    protected $signature = 'app:expire-rental-requests';

    protected $description = 'Expire rental requests, quotes and payment holds whose time has run out, and close their open PayMongo payment pages.';

    public function handle(RentalExpiryService $service): int
    {
        $result = $service->run();

        $this->info("Requests expired because the operator did not reply: {$result['pending']}");
        $this->info("Quotes expired because the passenger did not answer:  {$result['quoted']}");
        $this->info("Bookings expired because the passenger did not pay:   {$result['payment']}");
        $this->info("Leftover payment pages closed:                        {$result['swept']}");

        if ($result['pages_not_closed'] > 0) {
            $this->warn("{$result['pages_not_closed']} payment page(s) could not be closed. They will be retried on the next run.");
        }

        return self::SUCCESS;
    }
}