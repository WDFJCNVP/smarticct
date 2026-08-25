<?php

namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Events\PostActionEvent;
use App\Events\CreateNewPostEvent;
use App\Events\NotificationEvent;

use App\Models\RentTransaction;
use App\Models\TripRequest;
use App\Models\RentalOffer;
use App\Models\Post;
use App\Models\Notification;
use App\Models\UserNotification;

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

                // Let the commuter know their trip request was accepted.
                $notification = Notification::create([
                    'type'    => 'Accepted',
                    'title'   => 'Trip Request Accepted',
                    'message' => "Your trip request was accepted by {$interested_user->post->user->name}. You can view the details under your post's Active transaction tab.",
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id'         => $interested_user->user_id,
                ]);

            } elseif (auth()->user()->role === 'commuter') {

                RentalOffer::where('id', $interested_user->id)->update(['status' => 'accept']);

                $interested_user->post->update([
                    'status' => 'rented'
                ]);

                // Let the operator know their rental offer was accepted.
                $notification = Notification::create([
                    'type'    => 'Accepted',
                    'title'   => 'Rental Offer Accepted',
                    'message' => "Your rental offer was accepted by {$interested_user->post->user->name}. You can view the details under your post's Active transaction tab.",
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id'         => $interested_user->user_id,
                ]);

                // event(new PostActionEvent());

            }

            broadcast(new NotificationEvent());

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
            $tripRequest = TripRequest::updateOrCreate(
                [
                    'post_id' => $attributes['post_id'],
                    'user_id' => $attributes['user_id'],
                ],
                $attributes
            );

            // Let the post owner (operator) know someone sent a trip request.
            $post = Post::with('user')->find($attributes['post_id']);
            $requester = $tripRequest->user;

            if ($post && $post->user) {
                $notification = Notification::create([
                    'type'    => 'Trip Request',
                    'title'   => 'New Trip Request',
                    'message' => "{$requester->name} sent a trip request for your post. Check the details on your post's Interested commuters tab.",
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id'         => $post->user_id,
                ]);

                broadcast(new NotificationEvent());
            }
        });
    }
}