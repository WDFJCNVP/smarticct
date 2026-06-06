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
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Notification::class)->nullable()->constrained()->onDelete('set null');
            $table->foreignIdFor(\App\Models\User::class)->constrained()->onDelete('cascade');
            $table->boolean('is_read')->default(false);
            $table->boolean('admin_deleted')->default(false);
            $table->boolean('user_deleted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
