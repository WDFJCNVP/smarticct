<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;

use App\Models\PostInterest;

class PostService
{

    public function saveInterestedUser(array $attributes) {

        DB::transaction(function () use ($attributes) {
            PostInterest::create($attributes);
        });
    }
}
