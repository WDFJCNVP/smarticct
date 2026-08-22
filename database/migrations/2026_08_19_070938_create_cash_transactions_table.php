<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();

            // Who processed the cash payment (cashier)
            $table->foreignId('processed_by')
                ->constrained('users')
                ->onDelete('cascade');

            // The operator whose vehicle is being queued
            $table->foreignId('operator_id')
                ->constrained('users')
                ->onDelete('cascade');

            // The vehicle that was queued
            $table->foreignId('vehicle_id')
                ->constrained('vehicles')
                ->onDelete('cascade');

            // Link to the queue record
            $table->foreignId('queue_id')
                ->constrained('queues')
                ->onDelete('cascade');

            // Queue fee amount
            $table->decimal('amount', 10, 2);

            // Amount received from the customer
            $table->decimal('amount_received', 10, 2);

            // Change returned
            $table->decimal('change', 10, 2);

            // Optional reference / notes
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();

            // Payment status
            $table->enum('status', ['success', 'failed'])->default('success');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cash_transactions');
    }
};