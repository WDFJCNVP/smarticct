<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('login_lockout_level')->default(0);
            $table->timestamp('login_locked_until')->nullable();
            $table->timestamp('login_blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['login_lockout_level', 'login_locked_until', 'login_blocked_at']);
        });
    }
};
