<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Queue;
use App\Models\Vehicle;

class QueueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Vehicle::query()
            ->with(['user', 'route.terminal'])
            ->chunkById(100, function ($vehicles): void {
                foreach ($vehicles as $vehicle) {
                    Queue::factory()->fromVehicle($vehicle)->create();
                }
            });
    }
}
