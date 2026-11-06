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
        Schema::create('vehicle_event_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_event_type_id')->constrained()->restrictOnDelete();
 
            // Shown to passengers as "from PHP X". The final total is the operator's quote.
            $table->decimal('starting_price', 10, 2);
 
            // Fixed first payment (reservation fee) set by the operator for this
            // vehicle + event. It is deducted from the quoted total.
            $table->decimal('down_payment_amount', 10, 2);
 
            // Turn an event off for a vehicle without deleting the row,
            // so past requests keep their reference.
            $table->boolean('is_active')->default(true)->index();
 
            $table->timestamps();
 
            $table->unique(['vehicle_id', 'rental_event_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_event_rates');
    }
};
