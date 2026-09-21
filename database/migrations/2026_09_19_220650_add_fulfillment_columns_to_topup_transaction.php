<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('top_up_transactions', function (Blueprint $table) {
            $table->string('paymongo_event_id')->nullable()->unique()->after('checkout_session_id');
            $table->text('failure_reason')->nullable()->after('status');
            $table->decimal('points_credited', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('topup_transactions', function (Blueprint $table) {
            $table->dropColumn(['paymongo_event_id', 'failure_reason']);
            $table->decimal('points_credited', 12, 2)->nullable(false)->change();
        });
    }
};