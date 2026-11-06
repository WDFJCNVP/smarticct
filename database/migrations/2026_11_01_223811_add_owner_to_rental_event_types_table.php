<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_event_types', function (Blueprint $table) {
            // Names were unique across the whole table. Operators can now create their own
            // services, so uniqueness is per owner (user_id null = a standard event).
            $table->dropUnique(['name']);

            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('rental_event_types', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique('name');
        });
    }
};