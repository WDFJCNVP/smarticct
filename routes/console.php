<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

use App\Models\Post;
use App\Services\QueueManagementService;
use App\Services\DocumentExpiryNotificationService;

Schedule::call(function () {
    app(QueueManagementService::class)->generateSchedule(today());
})->daily();

// Operator-facing reminder: notify an operator when a vehicle's franchise
// is within 30 days of expiring, repeating twice a week (Mon & Thu) so the
// reminder keeps surfacing throughout the month leading up to expiry (see
// DocumentExpiryNotificationService for the windowing + de-dupe logic).
Schedule::call(function () {
    app(DocumentExpiryNotificationService::class)->notifyExpiringDocuments(30);
})->twiceWeekly(1, 4)->at('08:00');

Schedule::call(function () {
    // Posts sit in Trash for 30 days after being deleted (see Post::SoftDeletes),
    // then get purged for good here — attachments removed, row force-deleted.
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