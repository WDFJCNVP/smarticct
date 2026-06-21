<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(\App\Models\Vehicle::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(\App\Models\DailyScheduleSlot::class)->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('slot_position')->nullable()->index();
            $table->string('vehicle_type')->index();
            $table->string('destination')->index();
            $table->string('plate_number')->index();
            $table->string('driver_name');
            $table->enum('status', ['staging', 'loading', 'departed']);
            $table->integer('seat_capacity')->index();
            $table->integer('seat_count')->default(0)->index();
            $table->boolean('admin_deleted')->default(false)->index();
            $table->boolean('user_deleted')->default(false)->index();
            $table->timestamp('time_queued')->nullable()->index();
            $table->timestamp('departs_at')->nullable()->index();
            $table->timestamp('time_departed')->nullable()->index();
            $table->timestamps();

            $table->index(['destination', 'status']);
            $table->index('daily_schedule_slot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};