<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

use App\Models\Post;
use App\Services\QueueManagementService;
use App\Services\DocumentExpiryNotificationService;

// Schedule::command('app:notify-expiring-franchises')
//     ->everyMinute()
//     ->appendOutputTo(storage_path('logs/schedule.log'));

Schedule::call(function () {
    app(QueueManagementService::class)->generateSchedule(today());
})->daily();

Schedule::call(function () {
    app(DocumentExpiryNotificationService::class)->notifyExpiringDocuments(30);
})->cron('0 8 * * 1,4');

Schedule::call(function () {
    Post::onlyTrashed()
        ->where('deleted_at', '<=', now()->subDays(30))
        ->each(function (Post $post) {
            foreach (($post->metadata['attachments'] ?? []) as $attachmentPath) {
                Storage::disk('public')->delete($attachmentPath);
            }

            $post->forceDelete();
        });
})->daily();

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');