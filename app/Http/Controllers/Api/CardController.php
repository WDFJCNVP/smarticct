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

    private function getUserVehicle($card, $validated) {
        $vehicle = Vehicle::where('user_id', $card->user_id)->where('vehicle_type', $validated['vehicle_type'])->first(); 

        if (!$vehicle) {
            throw new \Exception('Vehicle Not Found');
        }

        return $vehicle;
    }

    private function isVehicleAlreadyQueued($card, $validated) {

        $vehicle = $this->getUserVehicle($card, $validated);

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

    private function queueOperatorVehicle($card, $validated) {

        $card_id = $card->id;

        $vehicle = $this->getUserVehicle($card, $validated);

        if (!$vehicle) {
            throw new \Exception('Vehicle Not Found');
        }

        $queue = Queue::where('status', 'loading')->where('destination', $validated['destination'])->where('vehicle_type', $vehicle->vehicle_type)->exists();

        if ($queue) {
            Queue::create([
                'card_id'       => $card->id,
                'vehicle_type'  => $vehicle->vehicle_type,
                'plate_number'  => $vehicle->plate_number,
                'driver_name'   => $vehicle->user->name,
                'seat_capacity' => $vehicle->total_seats,
                'seat_count'    => 0,
                'time_queued'   => now(),
                'time_departed' => null,
                'destination'   => $validated['destination'],
                'status'        => 'staging',
                'departs_at'    => null,
            ]);
        } else {
            $vehicle = $this->getUserVehicle($card, $validated);

            if (!$vehicle) {
                throw new \Exception('Vehicle Not Found');
            }

            $queue = Queue::create([
                'card_id'       => $card->id,
                'vehicle_type'  => $vehicle->vehicle_type,
                'plate_number'  => $vehicle->plate_number,
                'driver_name'   => $vehicle->user->name,
                'seat_capacity' => $vehicle->total_seats,
                'seat_count'    => 0,
                'time_queued'   => now(),
                'time_departed' => null,
                'destination'   =>  $validated['destination'],
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
                'name' => 'required|string|max:50',
                'transaction_type' => 'required|string|max:50',
                'amount' => 'nullable|numeric|min:0',
                'destination' => 'nullable|string',
                'vehicle_type' => 'nullable|string',
                'plate_number' => 'nullable|string',
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
            
            if ($validated['transaction_type'] === 'operator_payment'){
                
                $alreadyInQueue = $this->isVehicleAlreadyQueued($card, $validated);

                if ($alreadyInQueue) {
                    return response()->json([
                        'status' => 'failed',
                        'balanceAfter' => $balanceBefore,
                        'message'       => 'Vehicle is already in queue'
                    ]);
                    
                } else {
                    $result = $this->deductUserCard($validated, $amount, $balanceBefore, $balanceAfter, $message);

                    if ($result['status'] === 'success') {

                        $this->queueOperatorVehicle($card, $validated);
                    }

                    $status       = $result['status'];
                    $balanceAfter = $result['balanceAfter'];
                    $message      = $result['message'];
                }
            }

            // Create Card Transaction
            $transaction = CardTransaction::create([
                'card_id'           => $card->id,
                'amount'            => -$amount,
                'balance_before'    => $balanceBefore,
                'balance_after'     => $balanceAfter,
                'status'            => $status,
                'message'           => $message,
                'transaction_time' => now(),
            ]);

            // Return response
            return response()->json([
                'status' => $status,
                'message' => $message,
                'card_holder' => "{$card->user->name}",
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
