<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Events\NotificationEvent;

use App\Models\RentalOffer;

class PostInterestService
{
    public function create($selected_post, $validated_attributes)
    {
        return DB::transaction(function () use ($selected_post, $validated_attributes) {

            $storedAttachments = [];

            foreach ($validated_attributes['vehicle_images'] as $attachment) {
                $storedAttachments[] = $attachment->store('posts', 'public');
            }

            $post_interest = RentalOffer::create([
                'post_id' => $selected_post->id,
                'user_id' => auth()->id(),
                'vehicle_id' => $validated_attributes['vehicle_id'],
                'message' => $validated_attributes['message'],
                'status' => 'pending',
                'available_from'        => $validated_attributes['available_from'],
                'available_until'       => $validated_attributes['available_until'],
                'destination_coverage'  => $validated_attributes['destination_coverage'],
                'metadata' => [
                    'vehicle_name'          => $validated_attributes['vehicle_name'],
                    'vehicle_images'        => $storedAttachments,
                ]
            ]);

            event(new NotificationEvent());

            return $post_interest;

        });
    }
}
