<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{

    public function getUser(string $uid): JsonResponse
    {
        // Trim 
        $cleanUid = trim($uid);

        // 1. Check if the card exists 
        $card = Card::with('user')->where('uid', $cleanUid)->first();

        // 2. Guard
        if (! $card) {
            return response()->json([
                'status'  => 'error',
                'message' => 'RFID card not registered in the system.',
            ], 404);
        }

        // 3. Check if the card is assigned to a valid user
        if (! $card->user) {
            return response()->json([
                'status'  => 'warning',
                'message' => 'Card exists but is not linked to an active user profile.',
                'data'    => $card,
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'User and card details retrieved successfully.',
            'data'    => $card,
        ], 200);
    }
}