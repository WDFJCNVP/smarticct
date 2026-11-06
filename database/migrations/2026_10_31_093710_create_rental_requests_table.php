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
        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();
 
            // Who and what. Restrict deletes so rental history is never silently lost.
            $table->foreignId('passenger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_event_rate_id')->constrained()->restrictOnDelete();
 
            // Snapshot of what the passenger saw when requesting. Later edits to the
            // vehicle's rates must not change existing requests.
            $table->string('event_name');
            $table->decimal('starting_price', 10, 2);
            $table->decimal('down_payment_amount', 10, 2);
 
            // Typed route (no map): structured parts plus a free-text detail.
            $table->string('pickup_municipality');
            $table->string('pickup_barangay')->nullable();
            $table->string('pickup_details');
            $table->string('destination_municipality');
            $table->string('destination_barangay')->nullable();
            $table->string('destination_details');
 
            // Trip window and size.
            $table->dateTime('trip_start_at');
            $table->dateTime('trip_end_at');
            $table->unsignedSmallInteger('passenger_count');
            $table->text('notes')->nullable();
 
            // pending, declined, quoted, quote_rejected, awaiting_payment,
            // confirmed, completed, cancelled, expired
            $table->string('status')->default('pending')->index();
            $table->string('status_reason')->nullable(); // decline / cancel reason
 
            // Operator's response window for a pending request.
            $table->timestamp('respond_by')->nullable();
 
            // Quote.
            $table->decimal('quoted_total', 10, 2)->nullable();
            $table->string('quote_note')->nullable(); // what is included (fuel, toll, driver)
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('quote_expires_at')->nullable();
 
            // Passenger accepted the quote: dates are held while they pay.
            $table->timestamp('quote_accepted_at')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
 
            // The first payment itself (checkout session, PayMongo event, status,
            // failures) lives in rental_payments, because one request can have
            // several checkout attempts (failed, expired, retried).
 
            // Lifecycle timestamps.
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
 
            $table->timestamps();
 
            // Date-overlap checks per vehicle.
            $table->index(['vehicle_id', 'status', 'trip_start_at', 'trip_end_at'], 'rental_requests_overlap_index');
            $table->index(['passenger_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_requests');
    }
};
