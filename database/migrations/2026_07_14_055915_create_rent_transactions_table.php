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
        Schema::create('rent_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_owner_id');
            $table->foreignId('interested_user_id');
            $table->foreignIdFor(\App\Models\RentalOffer::class)->nullable();
            $table->foreignIdFor(\App\Models\TripRequest::class)->nullable();
            $table->enum('status',['ongoing', 'completed', 'cancelled'])->default('ongoing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rent_transactions');
    }
};
