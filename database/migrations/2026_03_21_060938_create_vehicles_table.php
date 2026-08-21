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

            $table->boolean('has_or_cr')->default(false);
            $table->date('or_cr_expiry_date')->nullable()->index();

            $table->boolean('has_franchise')->default(false);
            $table->date('franchise_expiry_date')->nullable()->index();

            $table->string('driver_name')->nullable();

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