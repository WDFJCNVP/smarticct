<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->integer('card_id');
            $table->string('vehicle_type');
            $table->string('destination');
            $table->string('plate_number');
            $table->string('driver_name');
            $table->string('status');
            $table->integer('seat_capacity');
            $table->integer('seat_count')->default(0);
            $table->timestamp('time_queued')->nullable();
            $table->timestamp('departs_at')->nullable();
            $table->timestamp('time_departed')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
