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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(App\Models\User::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(App\Models\RouteList::class)->nullable()->constrained()->onDelete('set null');
            $table->string('vehicle_type')->index();
            $table->string('plate_number')->index();
            $table->integer('total_seats')->index();

            $table->string('engine_number')->nullable();
            $table->string('body_number')->nullable();
            $table->string('chassis_number')->nullable();

            $table->boolean('has_franchise')->default(false);
            $table->date('franchise_expiry_date')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};