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
        Schema::create('trip_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Post::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete(); 
            $table->string('message');
            $table->enum('status', ['pending', 'accept', 'decline', 'cancel', 'completed'])->default('pending')->index();
            $table->enum('trip_type', ['one_way', 'round_trip']);
            $table->unsignedInteger('body_count');
            $table->string('pick_up_location');
            $table->string('drop_off_location');
            $table->date('trip_date');
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
        Schema::dropIfExists('trip_requests');
    }
};
