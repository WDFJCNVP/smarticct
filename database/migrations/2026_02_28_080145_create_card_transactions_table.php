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
            $table->foreignIdFor(\App\Models\Card::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()->index();
            $table->string('source')->nullable()->index();
            $table->decimal('points_deducted', 10, 2)->nullable();
            $table->enum('transaction_type', ['purchase', 'top-up', 'refund', 'adjustment'])->nullable()->index();
            $table->string('reference_no')->unique()->index();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('balance_before', 10, 2)->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->enum('status', ['success', 'failed', 'insufficient_balance'])->default('success')->index();
            $table->text('message')->nullable();
            $table->timestamp('transaction_time')->index();
            $table->json('metadata')->nullable();
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
