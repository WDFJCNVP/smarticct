<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

//Models
use App\Models\User;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Queue;
use App\Models\Vehicle;

//Job
use App\Jobs\ProcessAfterDepart;

//Event

use App\Events\QueuedVehicleEvent;

class CardController extends Controller
{
    private function getCard($uid) {
        return Card::with('user.vehicles')->where('uid', $uid)->first();
    }

    private function getUserVehicle($card, $vehicle_type) {

        $vehicle = Vehicle::where('user_id', $card->user->id)
            ->where('vehicle_type', $vehicle_type)
            ->first();

        if (!$vehicle) {
            throw new CardException('Vehicle not found', 404);
        }

        return $vehicle;
    }

    private function isVehicleAlreadyQueued($card, $vehicle_type) {

        $vehicle = $this->getUserVehicle($card, $vehicle_type);

        return Queue::where('plate_number', $vehicle->plate_number)
            ->whereIn('status', ['loading', 'staging'])
            ->exists();
    }

    private function deductUserCard($card, $validated, $amount, $balanceBefore, $balanceAfter, $message) {

        if ($balanceBefore < $amount) {
            throw new CardException("Insufficient balance. Available: {$balanceBefore}, Required: {$amount}", 402);
        } 
        else {
                $balanceAfter = $balanceBefore - $amount;
                $card->update(['balance' => $balanceAfter, 'updated_at' => now()]);
                
            return [
                'status'         => 'success',
                'balanceAfter'   => $balanceAfter,
                'message'        => "Fare payment successful. Balance:$balanceAfter",
            ];
        }
    }

    private function queueOperatorVehicle($card, $validated) {

        $card_id = $card->id;

        $vehicle = $this->getUserVehicle($card, $validated['vehicle_type']);

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
        
            $validated = $request->validate([
                'uid' => 'required|string|max:50',
                'name' => 'required|string|max:50',
                'transaction_type' => 'required|string|max:50',
                'amount' => 'nullable|numeric|min:0',
                'destination' => 'nullable|string',
                'vehicle_type' => 'nullable|string',
                'plate_number' => 'nullable|string',
            ]);

            Log::info('Card tap received', $validated);

            $card = $this->getCard($validated['uid']);

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
            $transaction_type = $validated['transaction_type'];

            if ($validated['transaction_type'] === 'fare_payment') {
                
                $queue = Queue::where('status', 'loading')->where('destination', $validated['destination'])->where('vehicle_type', $validated['vehicle_type'])->first();

                if (!$queue) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No loading vehicles found for the specified destination.',
                    ], 404);
                }

                $result = $this->deductUserCard($card, $validated, $amount, $balanceBefore, $balanceAfter, $message);

                if ($result['status'] === 'success') {
                    $queue->increment('seat_count');
                }

                $status       = $result['status'];
                $balanceAfter = $result['balanceAfter'];
                $message      = $result['message'];

                broadcast(new QueuedVehicleEvent());

            }
            
            if ($validated['transaction_type'] === 'operator_payment'){
                
                $alreadyInQueue = $this->isVehicleAlreadyQueued($card, $validated['vehicle_type']);

                if ($alreadyInQueue) {
                    return response()->json([
                        'status'        => 'failed',
                        'balanceAfter'  => $balanceBefore,
                        'message'       => 'Vehicle is already in queue'
                    ]);
                    
                } else {

                    $result = $this->deductUserCard($card, $validated, $amount, $balanceBefore, $balanceAfter, $message);

                    if ($result['status'] === 'success') {

                        $this->queueOperatorVehicle($card, $validated);
                    }

                    $status       = $result['status'];
                    $balanceAfter = $result['balanceAfter'];
                    $message      = $result['message'];

                    broadcast(new QueuedVehicleEvent());
                }
            }

            $transaction = CardTransaction::create([
                'card_id'           => $card->id,
                'points_deducted'   => -$amount,
                'transaction_type'  => $transaction_type,
                'amount'            => $amount,
                'balance_before'    => $balanceBefore,
                'balance_after'     => $balanceAfter,
                'status'            => $status,
                'message'           => $message,
                'transaction_time' => now(),
            ]);

            return response()->json([
                'status' => $status,
                'message' => $message,
                'transaction_type' => $transaction_type,
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
