<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;

use App\Events\RegistrationTapCardEvent;

class RfidCardRegistrationController extends Controller
{
    public function store(Request $request)
    {
        // Validate secret so only your Node.js can call this
        // if ($request->header('X-RFID-Secret') !== config('app.rfid_secret')) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        $request->validate(['uid' => 'required|string']);

        // Clear old pending taps first
        Card::where('status', 'pending')->delete();

        // Insert new tap
        $card = Card::create([
            'uid'    => $request->uid,
            'status' => 'pending',
        ]);

        broadcast(new RegistrationTapCardEvent($card));

        return response()->json([
            'success' => true,
            'uid'     => $card->uid,
        ]);
    }
}
