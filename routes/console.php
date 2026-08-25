<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

use App\Models\Post;
use App\Services\QueueManagementService;

Schedule::call(function () {
    app(QueueManagementService::class)->generateSchedule(today());
})->daily();

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