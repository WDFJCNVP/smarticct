<?php

namespace App\Console\Commands;

use App\Models\DailyScheduleSlot;
use App\Services\QueueManagementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-daily-schedule')]
#[Description('Generate today\'s first-round group schedule')]
class GenerateDailySchedule extends Command
{
    public function handle(QueueManagementService $service): int
    {
        if (DailyScheduleSlot::where('schedule_date', today()->toDateString())->exists()) {
            $this->info('Schedule already exists for today, skipped.');
            return self::SUCCESS;
        }

        $result = $service->generateSchedule(today());
        $this->info($result['message']);

        return self::SUCCESS;
    }
}