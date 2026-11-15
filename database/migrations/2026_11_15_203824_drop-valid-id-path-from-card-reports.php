<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_reports', function (Blueprint $table) {
            $table->dropColumn('valid_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('card_reports', function (Blueprint $table) {
            $table->string('valid_id_path')->nullable()->after('card_id');
        });
    }
};