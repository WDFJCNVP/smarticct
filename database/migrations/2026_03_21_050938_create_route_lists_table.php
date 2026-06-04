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
        Schema::create('route_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Terminal::class)->constrained()->onDelete('cascade');
            $table->string('vehicle_type');
            $table->string('first_trip')->nullable();
            $table->string('last_trip')->nullable();
            $table->decimal('base_fare', 10, 2);
            $table->enum('type', ['fare', 'operator_tickets']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_lists');
    }
};
