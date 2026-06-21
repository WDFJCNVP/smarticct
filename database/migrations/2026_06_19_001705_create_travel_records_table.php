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
        Schema::create('travel_records', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(\App\Models\User::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignIdFor(\App\Models\CardTransaction::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignIdFor(\App\Models\Queue::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('destination');
            $table->string('vehicle_type');
            $table->string('plate_number');
            $table->string('driver_name');

            $table->string('commuter_type')->nullable();

            $table->decimal('fare_amount', 10, 2)->nullable();

            $table->timestamp('departed_at');

            $table->timestamps();

            $table->index('user_id');
            $table->index('departed_at');
            $table->index('commuter_type');
            $table->index(['destination', 'departed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_records');
    }
};
