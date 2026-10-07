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
        Schema::create('terminal_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->default('main');
            $table->decimal('balance', 12, 2)->default(0);        // withdrawable now
            $table->decimal('total_earned', 12, 2)->default(0);   // lifetime card queue fees
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_accounts');
    }
};
