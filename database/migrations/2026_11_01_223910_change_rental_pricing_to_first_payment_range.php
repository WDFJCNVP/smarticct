<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Before: a fixed first payment (down_payment_amount) and a starting price per event.
     * After:  a first payment RANGE (min / max) per event, and the exact first payment is
     *         chosen by the operator in each quote (quoted_first_payment).
     *
     * Existing rows keep what was entered: the old fixed first payment becomes a range of
     * one value (min = max), so nothing breaks and operators can widen it later.
     */
    public function up(): void
    {
        // ---------------- vehicle_event_rates ----------------
        Schema::table('vehicle_event_rates', function (Blueprint $table) {
            $table->decimal('first_payment_min', 10, 2)->nullable()->after('rental_event_type_id');
            $table->decimal('first_payment_max', 10, 2)->nullable()->after('first_payment_min');
        });

        DB::statement('UPDATE vehicle_event_rates SET first_payment_min = down_payment_amount, first_payment_max = down_payment_amount');

        Schema::table('vehicle_event_rates', function (Blueprint $table) {
            $table->decimal('first_payment_min', 10, 2)->nullable(false)->change();
            $table->decimal('first_payment_max', 10, 2)->nullable(false)->change();
        });

        Schema::table('vehicle_event_rates', function (Blueprint $table) {
            $table->dropColumn(['starting_price', 'down_payment_amount']);
        });

        // ---------------- rental_requests ----------------
        Schema::table('rental_requests', function (Blueprint $table) {
            // Snapshot of the range the passenger saw when requesting.
            $table->decimal('first_payment_min', 10, 2)->nullable()->after('event_name');
            $table->decimal('first_payment_max', 10, 2)->nullable()->after('first_payment_min');
            // The exact first payment the operator chose in the quote (paid online).
            $table->decimal('quoted_first_payment', 10, 2)->nullable()->after('quoted_total');
        });

        DB::statement('UPDATE rental_requests SET first_payment_min = down_payment_amount, first_payment_max = down_payment_amount');
        DB::statement('UPDATE rental_requests SET quoted_first_payment = down_payment_amount WHERE quoted_total IS NOT NULL');

        Schema::table('rental_requests', function (Blueprint $table) {
            $table->decimal('first_payment_min', 10, 2)->nullable(false)->change();
            $table->decimal('first_payment_max', 10, 2)->nullable(false)->change();
        });

        Schema::table('rental_requests', function (Blueprint $table) {
            $table->dropColumn(['starting_price', 'down_payment_amount']);
        });
    }

    public function down(): void
    {
        // ---------------- rental_requests ----------------
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->decimal('down_payment_amount', 10, 2)->nullable();
        });

        DB::statement('UPDATE rental_requests SET down_payment_amount = COALESCE(quoted_first_payment, first_payment_min), starting_price = first_payment_max');

        Schema::table('rental_requests', function (Blueprint $table) {
            $table->decimal('starting_price', 10, 2)->nullable(false)->change();
            $table->decimal('down_payment_amount', 10, 2)->nullable(false)->change();
        });

        Schema::table('rental_requests', function (Blueprint $table) {
            $table->dropColumn(['first_payment_min', 'first_payment_max', 'quoted_first_payment']);
        });

        // ---------------- vehicle_event_rates ----------------
        Schema::table('vehicle_event_rates', function (Blueprint $table) {
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->decimal('down_payment_amount', 10, 2)->nullable();
        });

        DB::statement('UPDATE vehicle_event_rates SET down_payment_amount = first_payment_min, starting_price = first_payment_max');

        Schema::table('vehicle_event_rates', function (Blueprint $table) {
            $table->decimal('starting_price', 10, 2)->nullable(false)->change();
            $table->decimal('down_payment_amount', 10, 2)->nullable(false)->change();
        });

        Schema::table('vehicle_event_rates', function (Blueprint $table) {
            $table->dropColumn(['first_payment_min', 'first_payment_max']);
        });
    }
};