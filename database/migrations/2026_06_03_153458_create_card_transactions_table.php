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
            $table->foreignIdFor(\App\Models\Card::class)->constrained()->onDelete('cascade');
            $table->decimal('points_deducted', 10, 2)->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('balance_before', 10, 2)->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->enum('status', ['success', 'failed', 'insufficient_balance'])->default('success');
            $table->text('message')->nullable();
            $table->timestamp('transaction_time');
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
