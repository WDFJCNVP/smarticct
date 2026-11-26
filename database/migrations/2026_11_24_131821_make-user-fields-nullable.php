<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operators imported from the old terminal records have no account:
 * no password, and an email only if the old record had one.
 *
 * Raw ALTERs are used on purpose: they are idempotent (safe if a column was
 * already made nullable by an earlier migration) and avoid ->change() quirks.
 * PostgreSQL syntax (your app runs on pgsql). A NULL email does not break the
 * unique index; PostgreSQL allows many NULLs in a unique column.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN email_address DROP NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
    }

    public function down(): void
    {
        $blocked = DB::table('users')
            ->whereNull('password')
            ->orWhereNull('email_address')
            ->exists();

        if ($blocked) {
            throw new RuntimeException(
                'Cannot roll back: some users have no password or email. Delete or complete those records first.'
            );
        }

        DB::statement('ALTER TABLE users ALTER COLUMN email_address SET NOT NULL');
        DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
    }
};