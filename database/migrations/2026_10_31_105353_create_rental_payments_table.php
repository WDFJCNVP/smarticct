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
        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // passenger who paid
 
            $table->string('checkout_session_id')->unique();
 
            $table->decimal('amount', 10, 2);                    // amount asked for (the first payment)
            $table->decimal('amount_confirmed', 10, 2)->nullable(); // amount PayMongo confirmed
 
            $table->string('payment_method')->nullable();        // gcash, paymaya, card, qrph
            $table->string('status')->default('pending')->index(); // pending, paid, failed, expired
            $table->string('failure_reason')->nullable();
            $table->string('paymongo_event_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
    }
};
