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
        Schema::create('vehicle_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Vehicle::class)->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('group_number');
            $table->unsignedSmallInteger('order_number');

            $table->unique('vehicle_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_groups');
    }
};
