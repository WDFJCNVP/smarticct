<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'rental_request_id', 'user_id', 'checkout_session_id', 'amount', 'amount_confirmed',
        'payment_method', 'status', 'failure_reason', 'paymongo_event_id', 'paid_at',
    ];

    /**
     * One row per PayMongo checkout attempt for a rental's first payment.
     *
     * Safe to run in any state:
     *  - table missing                      -> it is created
     *  - table exists with all the columns  -> nothing happens
     *  - table exists with wrong columns    -> rebuilt, but ONLY if it has no rows
     *    (for example an empty table made by `php artisan make:model RentalPayment -m`)
     */
    public function up(): void
    {
        if (Schema::hasTable('rental_payments')) {
            if (Schema::hasColumns('rental_payments', self::COLUMNS)) {
                return;
            }

            if (DB::table('rental_payments')->exists()) {
                throw new RuntimeException(
                    'The rental_payments table exists with the wrong columns and already has rows. '
                    . 'Look at the rows, drop the table by hand if they are test data, then run migrate again.'
                );
            }

            Schema::drop('rental_payments');
        }

        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // passenger who paid

            $table->string('checkout_session_id')->unique();

            $table->decimal('amount', 10, 2);                       // amount asked for (the quoted first payment)
            $table->decimal('amount_confirmed', 10, 2)->nullable(); // amount PayMongo confirmed

            $table->string('payment_method')->nullable();           // gcash, paymaya, card, qrph
            // pending, failed, expired, paid, needs_review
            $table->string('status')->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->string('paymongo_event_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_payments');
    }
};