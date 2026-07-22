<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;

use App\Models\TripRequest;

class PostService
{

    public function saveTripRequest(array $attributes) {

        DB::transaction(function () use ($attributes) {
            TripRequest::create($attributes);
        });
    }
}
