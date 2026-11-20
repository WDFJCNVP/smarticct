<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

use App\Models\Post;
use App\Services\QueueManagementService;
use App\Services\DocumentExpiryNotificationService;

Schedule::command('app:notify-expiring-franchises')
    ->dailyAt('00:00')
    ->appendOutputTo(storage_path('logs/schedule.log'));

Schedule::command('app:generate-daily-schedule')->dailyAt('00:00');

Schedule::call(function () {
    app(DocumentExpiryNotificationService::class)->notifyExpiringDocuments(30);
})->dailyAt('00:00');

// Schedule::call(function () {
//     Post::onlyTrashed()
//         ->where('deleted_at', '<=', now()->subDays(30))
//         ->each(function (Post $post) {
//             foreach (($post->metadata['attachments'] ?? []) as $attachmentPath) {
//                 Storage::disk('public')->delete($attachmentPath);
//             }

//             $post->forceDelete();
//         });
// })->daily();

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');