<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The full payment is no longer set per service. The operator sets it in the quote
     * of each request, because every trip (route, number of passengers) is different.
     * Does nothing when the column was never added.
     */
    public function up(): void
    {
        if (Schema::hasColumn('vehicle_event_rates', 'full_payment_amount')) {
            Schema::table('vehicle_event_rates', function (Blueprint $table) {
                $table->dropColumn('full_payment_amount');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vehicle_event_rates', 'full_payment_amount')) {
            Schema::table('vehicle_event_rates', function (Blueprint $table) {
                $table->decimal('full_payment_amount', 10, 2)->nullable();
            });
        }
    }
};