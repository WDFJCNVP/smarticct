<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // If your status column is a string (varchar) this is all you need.
        // If it's an enum, use the DB statement below instead.
        DB::statement("ALTER TABLE queues MODIFY COLUMN status ENUM('staging','paid','loading','departed','skipped') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE queues MODIFY COLUMN status ENUM('staging','loading','departed','skipped') NOT NULL");
    }
};