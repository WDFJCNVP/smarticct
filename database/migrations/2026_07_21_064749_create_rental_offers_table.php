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
        Schema::create('rental_offers', function (Blueprint $table) {
           $table->id();
            $table->foreignIdFor(\App\Models\Post::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Vehicle::class)->constrained()->cascadeOnDelete();
            $table->string('message');
            $table->enum('status', ['pending', 'accept', 'decline', 'cancel', 'completed'])->default('pending')->index();
            $table->string('destination_coverage')->nullable();
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_offers');
    }
};
