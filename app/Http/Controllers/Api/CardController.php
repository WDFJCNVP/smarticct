<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

//Models
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Queue;
use App\Models\Vehicle;

//Job
use App\Jobs\ProcessAfterDepart;

class CardController extends Controller
{

    private function getUserVehicle($userId) {
        $userVehicle = Vehicle::with('route.terminal')->where('user_id', $userId)->first(); 
        return $userVehicle;
    }

    private function isVehicleAlreadyQueued($userId) {
        $vehicle = $this->getUserVehicle($userId);

        if (!$vehicle) {
            throw new \Exception('Vehicle Not Found');
        }

        //return true
        return Queue::where('plate_number', $vehicle->plate_number)
            ->whereIn('status', ['loading', 'staging'])
            ->exists();
    }

    private function deductUserCard($validated, $amount, $balanceBefore, $balanceAfter, $message) {

        if ($balanceBefore < $amount) {
            return [
                'status'        => 'insufficient_balance',
                'balanceAfter'  => $balanceAfter,
                'message'       => "Insufficient balance. Available:{$balanceBefore}, Required:{$amount}",
            ];

        } else {
            $balanceAfter = $balanceBefore - $amount;
            
            // Update card balance
            $card = Card::where('uid', $validated['uid'])->update(['balance' => $balanceAfter, 'updated_at' => now()]);
            
           return [
            'status'         => 'success',
            'balanceAfter'   => $balanceAfter,
            'message'        => "Fare payment successful. Balance:$balanceAfter",
           ];
        }
    }

    private function queueOperatorVehicle($card) {

        $vehicle = $this->getUserVehicle($card->user_id);
        $destination = $vehicle->route->terminal->municipality;

        $queue = Queue::where('status', 'loading')->where('destination', $destination)->where('vehicle_type', $vehicle->vehicle_type)->exists();

        if ($queue) {

            if (!$vehicle) {
                throw new \Exception('Vehicle Not Found');
            }

            $driver_full_name = $vehicle->user->first_name . ' ' . $vehicle->user->last_name;

            Queue::create([
                'vehicle_type'  => $vehicle->vehicle_type,
                'plate_number'  => $vehicle->plate_number,
                'driver_name'   => $driver_full_name,
                'seat_capacity' => $vehicle->total_seats,
                'seat_count'    => 0,
                'time_queued'   => now(),
                'time_departed' => null,
                'destination'   => $destination,
                'status'        => 'staging',
                'departs_at'    => null,
            ]);
        } else {
            $vehicle = $this->getUserVehicle($card->user_id);

            if (!$vehicle) {
                throw new \Exception('Vehicle Not Found');
            }

            $driver_full_name = $vehicle->user->first_name . ' ' . $vehicle->user->last_name;

            $queue = Queue::create([
                'vehicle_type'  => $vehicle->vehicle_type,
                'plate_number'  => $vehicle->plate_number,
                'driver_name'   => $driver_full_name,
                'seat_capacity' => $vehicle->total_seats,
                'seat_count'    => 0,
                'time_queued'   => now(),
                'time_departed' => null,
                'destination'   => $destination,
                'status'        => 'loading',
                'departs_at'    => Carbon::now()->addMinute(),
            ]);

            ProcessAfterDepart::dispatch($queue->id)
                ->delay($queue->departs_at);        
        }
    }

    public function tap(Request $request) {
        try {
            // Validate request
            $validated = $request->validate([
                'uid' => 'required|string|max:50',
                'transaction_type' => 'required|string|max:50',
                'amount' => 'nullable|numeric|min:0',
                'device_id' => 'nullable|string|max:50',
                'location' => 'nullable|string|max:255',
                'destination' => 'nullable|string',
                'vehicle_type' => 'nullable|string',
            ]);

            // Log incoming request
            Log::info('Card tap received', $validated);

            // Find card
            $card = Card::where('uid', $validated['uid'])->first();

            if (!$card) {
                return response()->json([
                    'success' => false,
                    'message' => 'Card not found',
                ], 404);
            }

            if ($card->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Card is ' . $card->status,
                ], 403);
            }

            $balanceBefore = $card->balance;
            $balanceAfter = $balanceBefore;
            $status = '';
            $message = '';
            $amount = $validated['amount'] ?? 0;

            // Deduct User Card and Queue Operator
            if ($validated['transaction_type'] === 'fare_payment') {
                
                $queue = Queue::where('status', 'loading')->where('destination', $validated['destination'])->where('vehicle_type', $validated['vehicle_type'])->first();

                if (!$queue) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No loading vehicles found for the specified destination.',
                    ], 404);
                }

                $result = $this->deductUserCard($validated, $amount, $balanceBefore, $balanceAfter, $message);

                if ($result['status'] === 'success') {
                    $queue->increment('seat_count');
                }

                $status       = $result['status'];
                $balanceAfter = $result['balanceAfter'];
                $message      = $result['message'];

            }
            
            if ($validated['transaction_type'] === 'operator_payment') {
                
                if ($this->isVehicleAlreadyQueued($card->user_id)) {
                    $status       = 'failed';
                    $balanceAfter = $balanceBefore;
                    $message      = 'Vehicle is already in queue.';
                    
                } else {
                    $result = $this->deductUserCard($validated, $amount, $balanceBefore, $balanceAfter, $message);

                    if ($result['status'] === 'success') {
                        $this->queueOperatorVehicle($card);
                    }

                    $status       = $result['status'];
                    $balanceAfter = $result['balanceAfter'];
                    $message      = $result['message'];
                }
            }

            // Create Card Transaction
            $transaction = CardTransaction::create([
                'uid'               => $validated['uid'],
                'transaction_type'  => $validated['transaction_type'],
                'amount'            => -$amount,
                'balance_before'    => $balanceBefore,
                'balance_after'     => $balanceAfter,
                'device_id'         => $validated['device_id'] ?? null,
                'location'          => $validated['location'] ?? null,
                'status'            => $status,
                'message'           => $message,
                'transaction_time'  => now(),
            ]);

            // Return response
            return response()->json([
                'status' => $status,
                'message' => $message,
                'card_holder' => "{$card->user->first_name} {$card->user->last_name}",
                'card_type' => $card->user->role,
                'balance_before' => (float) $balanceBefore,
                'balance_after' => (float) $balanceAfter,
                'transaction_id' => $transaction->id,
                'timestamp' => $transaction->transaction_time->toIso8601String(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Card tap error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
