<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('commuter_type', ['regular', 'student', 'senior', 'pwd'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('commuter_type', ['regular', 'student', 'senior', 'pwd'])
                ->nullable(false)
                ->change();
        });
    }
};