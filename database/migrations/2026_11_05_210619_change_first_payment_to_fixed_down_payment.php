<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Target pricing model
     *   vehicle_event_rates.down_payment_amount  fixed first payment, paid online (operator can edit anytime)
     *   rental_requests.down_payment_amount      the first payment the passenger saw when requesting
     *   (quoted_first_payment / quoted_total on rental_requests are unchanged)
     *
     * It works whichever way your database currently looks:
     *   - original columns:  starting_price + down_payment_amount
     *   - range columns:     first_payment_min + first_payment_max
     * and it only adds or drops a column when needed, so running it twice is harmless.
     */
    public function up(): void
    {
        $this->rates();
        $this->requests();
    }

    private function rates(): void
    {
        $table = 'vehicle_event_rates';

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (! Schema::hasColumn($table, 'down_payment_amount')) {
                $t->decimal('down_payment_amount', 10, 2)->nullable();
            }
        });

        // Keep what operators already entered.
        if (Schema::hasColumn($table, 'first_payment_min')) {
            DB::statement("UPDATE {$table} SET down_payment_amount = first_payment_min WHERE down_payment_amount IS NULL");
        }

        // A rate must always have its first payment. The full payment stays optional here
        // (operators fill it in on the setup page) so no made-up number is stored.
        if (! DB::table($table)->whereNull('down_payment_amount')->exists()) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('down_payment_amount', 10, 2)->nullable(false)->change();
            });
        }

        $this->dropIfPresent($table, ['starting_price', 'first_payment_min', 'first_payment_max', 'full_payment_amount']);
    }

    private function requests(): void
    {
        $table = 'rental_requests';

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (! Schema::hasColumn($table, 'down_payment_amount')) {
                $t->decimal('down_payment_amount', 10, 2)->nullable();
            }
            if (! Schema::hasColumn($table, 'quoted_first_payment')) {
                $t->decimal('quoted_first_payment', 10, 2)->nullable();
            }
        });

        if (Schema::hasColumn($table, 'first_payment_min')) {
            DB::statement("UPDATE {$table} SET down_payment_amount = first_payment_min WHERE down_payment_amount IS NULL");
        }

        // Quotes already sent keep the first payment they were sent with.
        DB::statement("UPDATE {$table} SET quoted_first_payment = down_payment_amount WHERE quoted_total IS NOT NULL AND quoted_first_payment IS NULL");

        if (! DB::table($table)->whereNull('down_payment_amount')->exists()) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('down_payment_amount', 10, 2)->nullable(false)->change();
            });
        }

        $this->dropIfPresent($table, ['starting_price', 'first_payment_min', 'first_payment_max']);
    }

    private function dropIfPresent(string $table, array $columns): void
    {
        $present = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));

        if ($present !== []) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
        }
    }

    public function down(): void
    {
        // Back to the range model (values are approximate: min = max = the fixed down payment).
        foreach (['vehicle_event_rates', 'rental_requests'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'first_payment_min')) {
                    $t->decimal('first_payment_min', 10, 2)->nullable();
                }
                if (! Schema::hasColumn($table, 'first_payment_max')) {
                    $t->decimal('first_payment_max', 10, 2)->nullable();
                }
            });

            DB::statement("UPDATE {$table} SET first_payment_min = down_payment_amount, first_payment_max = down_payment_amount");

            Schema::table($table, function (Blueprint $t) {
                $t->decimal('first_payment_min', 10, 2)->nullable(false)->change();
                $t->decimal('first_payment_max', 10, 2)->nullable(false)->change();
            });
        }

        $this->dropIfPresent('vehicle_event_rates', ['down_payment_amount']);
        $this->dropIfPresent('rental_requests', ['down_payment_amount']);
    }
};