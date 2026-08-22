<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Events\PostActionEvent;
use App\Events\CreateNewPostEvent;

use App\Models\RentTransaction;
use App\Models\TripRequest;
use App\Models\RentalOffer;
use App\Models\Post;

class PostService
{

    public function createRentalTransaction($attributes, $interested_user) {

        $rent_transaction = DB::transaction(function () use ($attributes, $interested_user) {

            $transaction = RentTransaction::create($attributes);

            if(auth()->user()->role === 'operator') {

                TripRequest::where('id', $interested_user->id)->update(['status' => 'accept']);

                $interested_user->post->update([
                    'status' => 'rented'
                ]);

            } elseif (auth()->user()->role === 'commuter') {

                RentalOffer::where('id', $interested_user->id)->update(['status' => 'accept']);

                $interested_user->post->update([
                    'status' => 'rented'
                ]);

                // event(new PostActionEvent());

            }

            return $transaction;
        });

        return $rent_transaction;

    }

    public function createPost($attributes) {

        $post = DB::transaction(function () use ($attributes) {
            $post = Post::create($attributes);
            return $post;
        });

        broadcast(new CreateNewPostEvent());

        return $post;
    }

    public function saveTripRequest(array $attributes) {

        DB::transaction(function () use ($attributes) {
            TripRequest::updateOrCreate(
                [
                    'post_id' => $attributes['post_id'],
                    'user_id' => $attributes['user_id'],
                ],
                $attributes
            );
        });
    }
}