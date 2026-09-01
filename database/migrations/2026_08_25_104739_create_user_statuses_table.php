<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_statuses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('status', ['active', 'suspended'])->default('active')->index();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->foreignId('suspended_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Set when a commuter permanently deletes their own account
            // (see UserService::deleteOwnAccount()). Kept separate from
            // the `status` enum above so the account can be blocked from
            // logging in without needing an enum/CHECK constraint change.
            // The related User row is intentionally NOT soft-deleted, so
            // other users' transaction/travel history can still resolve
            // it and display "Deleted User".
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at_by_user')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_statuses');
    }
};
