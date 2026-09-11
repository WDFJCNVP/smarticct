<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Card;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{

    public function getUser(string $uid): JsonResponse
    {
        // Trim 
        $cleanUid = trim($uid);

        //Check if the card exists 
        $card = Card::with('user.vehicles.route_list.operatorTicketRate')->where('uid', $cleanUid)->first();

        //Guard
        if (! $card) {
            return response()->json([
                'status'  => 'error',
                'message' => 'RFID card not registered in the system.',
            ], 404);
        }

        //Check if the card is assigned to a valid user
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

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_address' => 'required|string', 
            'password'      => 'required|string',
        ]);

        $user = User::where('email_address', $validated['email_address'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $card = Card::with('user.vehicles.route_list.operatorTicketRate')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $card) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Your account does not have an active RFID card linked. Please visit the counter.',
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Authentication successful',
            'data'    => $card,
        ], 200);
    }
}