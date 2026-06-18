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
            $table->foreignId('daily_schedule_slot_id')
                  ->nullable()
                  ->constrained('daily_schedule_slots')
                  ->nullOnDelete();
            $table->unsignedSmallInteger('slot_position')->nullable();
            $table->string('vehicle_type');
            $table->string('destination');
            $table->string('plate_number');
            $table->string('driver_name');
            $table->string('status');
            $table->integer('seat_capacity');
            $table->integer('seat_count')->default(0);
            $table->boolean('admin_deleted')->default(false);
            $table->boolean('user_deleted')->default(false);
            $table->timestamp('time_queued')->nullable();
            $table->timestamp('departs_at')->nullable();
            $table->timestamp('time_departed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};