<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Card;
use App\Models\User;
use App\Models\RouteList;
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
            'route_list'    => $this->getRouteList(),
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
            'status'        => 'success',
            'message'       => 'Authentication successful',
            'data'          => $card,
            'route_list'    => $this->getRouteList(),
        ], 200);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin'     => 'required|string|size:6',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::with('card')->findOrFail($validated['user_id']);

        if (!$user->card) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No card is linked to this account.',
            ], 404);
        }

        $card = $user->card;

        if ($card->pin_locked_at) {
            app(AuditLogsService::class)->create([
                'user_id'  => $user->id,
                'action'   => 'kiosk_pin_blocked',
                'subject'  => "Locked card tap attempt - {$user->user_code}",
                'channel'  => 'Kiosk',
                'metadata' => ['ip_address' => $request->ip()],
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'This card is locked due to too many incorrect PIN attempts. Please visit the terminal office to have it unlocked.',
            ], 423);
        }

        if (!Hash::check($validated['pin'], $card->pin)) {
            $card->increment('pin_attempts');

            if ($card->pin_attempts >= 5) {
                $card->update(['pin_locked_at' => now()]);

                app(AuditLogsService::class)->create([
                    'user_id'  => $user->id,
                    'action'   => 'kiosk_pin_lockout',
                    'subject'  => "Card locked after repeated failed PIN attempts - {$user->user_code}",
                    'channel'  => 'Kiosk',
                    'metadata' => ['ip_address' => $request->ip()],
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Too many incorrect attempts. This card is now locked — please visit the terminal office.',
                ], 423);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'PIN number is incorrect. Please try again.',
            ], 401);
        }

        if ($card->pin_attempts > 0) {
            $card->update(['pin_attempts' => 0, 'pin_locked_at' => null]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Authentication successful',
            'data'    => [
                'user_id'   => $user->id,
                'user_code' => $user->user_code,
                'name'      => $user->name,
                'balance'   => $card->balance,
            ],
        ], 200);
    }

    public function getRouteList() {

        $data = RouteList::with('operatorTicketRate')->get();

        return $data;
    }
}