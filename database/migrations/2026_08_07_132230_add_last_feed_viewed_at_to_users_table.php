<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add timestamp to users table
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_feed_viewed_at')->nullable()->after('remember_token');
        });

        // 2. Add performance index to posts table (makes post counting instant)
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['created_at', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_feed_viewed_at');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'user_id', 'status']);
        });
    }
};