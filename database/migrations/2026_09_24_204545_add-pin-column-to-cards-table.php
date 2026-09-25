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
        Schema::table('cards', function (Blueprint $table) {
            $table->string('pin', 60)->nullable()->after('uid');
            $table->unsignedTinyInteger('pin_attempts')->default(0)->after('pin');
            $table->timestamp('pin_locked_at')->nullable()->after('pin_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['pin', 'pin_attempts', 'pin_locked_at']);
        });
    }
};