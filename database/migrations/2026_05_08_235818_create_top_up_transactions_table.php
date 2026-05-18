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
        Schema::create('top_up_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(\App\Models\Card::class)->constrained()->onDelete('cascade');
            $table->string('checkout_session_id')->nullable(); // from PayMongo
            $table->integer('points_to_load');                 // e.g. 100
            $table->decimal('amount_paid', 10, 2);             // e.g. 100.00
            $table->string('payment_method')->nullable();      // gcash/paymaya/card
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('top_up_transactions');
    }
};
