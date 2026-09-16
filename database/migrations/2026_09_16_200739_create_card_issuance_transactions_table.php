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
        Schema::create('card_issuance_transactions', function (Blueprint $table) {
            $table->id();

            // The card that was issued/replaced.
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();

            // Who the card belongs to (commuter/operator).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Cashier/admin who processed the issuance.
            $table->foreignId('processed_by')->constrained('users')->cascadeOnDelete();

            $table->enum('issuance_type', ['new', 'replacement'])->default('new');

            $table->decimal('amount', 8, 2);
            $table->decimal('amount_received', 8, 2)->nullable();
            $table->decimal('change', 8, 2)->nullable();

            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['success', 'failed'])->default('success');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_issuance_transactions');
    }
};
