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
        Schema::create('card_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 50);
            $table->string('transaction_type', 50);
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('balance_before', 10, 2)->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->string('device_id', 50)->nullable();
            $table->string('location', 255)->nullable();
            $table->enum('status', ['success', 'failed', 'insufficient_balance'])->default('success');
            $table->text('message')->nullable();
            $table->timestamp('transaction_time')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_transactions');
    }
};
