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
        Schema::create('daily_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->date('schedule_date')->index();
            $table->foreignIdFor(\App\Models\VehicleGroup::class)->constrained()->onDelete('cascade');
            $table->enum('status', ['waiting', 'queued', 'departed'])->default('waiting')->index();

            $table->unsignedSmallInteger('slot_position')->index();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['schedule_date', 'vehicle_group_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_schedule_slots');
    }
};
