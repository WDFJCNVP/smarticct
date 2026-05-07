<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

use App\Models\Queue;

class ProcessAfterDepart implements ShouldQueue
{
    use Queueable;

    private $model_id;

    public function __construct($model_id)
    {
        $this->model_id = $model_id;
    }

    public function handle(): void
    {
        DB::transaction(function () {

            $queue = Queue::where('id', $this->model_id)
                ->lockForUpdate()
                ->first();

            if (!$queue || $queue->status !== 'loading') {
                Log::warning("Queue [{$this->model_id}] not found or not in loading state.");
                return;
            }

            $queue->update([
                'time_departed' => Carbon::now(),
                'status'        => 'departed',
            ]);

            Log::info("Queue [{$queue->id}] marked as departed.");

            $next_queue = Queue::where('status', 'staging')
                ->orderBy('time_queued', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$next_queue) {
                Log::info("No staging vehicles found. Queue chain ended.");
                return;
            }

            $next_queue->update([
                'status'     => 'loading',
                'departs_at' => Carbon::now()->addMinute(),
            ]);

            Log::info("Queue [{$next_queue->id}] promoted to loading.");

            ProcessAfterDepart::dispatch($next_queue->id)
                ->delay($next_queue->departs_at);
        });
    }
}