<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Queue;
use App\Models\Vehicle;
use App\Models\DailyScheduleSlot;
use App\Models\TravelRecord;
use App\Jobs\ProcessAfterDepart;
use App\Events\QueuedVehicleEvent;
use App\Events\TriggerDepartingEvent;

class CardController extends Controller
{
    private function getCard(string $uid)
    {
        return Card::with('user.vehicles')->where('uid', $uid)->first();
    }

    private function getUserVehicle(int $vehicleId): Vehicle
    {
        $vehicle = Vehicle::with('user', 'route_list.operatorTicketRate')->find($vehicleId);

        if (!$vehicle) {
            throw new \RuntimeException('Vehicle not found', 404);
        }

        return $vehicle;
    }

    private function isVehicleAlreadyQueued(string $plateNumber): bool
    {
        return Queue::where('plate_number', $plateNumber)
            ->whereIn('status', ['loading', 'staging'])
            ->exists();
    }

    private function deductUserCard(Card $card, float $amount, float $balanceBefore): array
    {
        if ($balanceBefore < $amount) {
            return [
                'success'      => false,
                'balanceAfter' => $balanceBefore,
                'message'      => "Payment unsuccessful. Balance:{$balanceBefore}. Required points:{$amount}",
            ];
        }

        $balanceAfter = $balanceBefore - $amount;
        $card->update(['balance' => $balanceAfter, 'updated_at' => now()]);

        return [
            'success'      => true,
            'balanceAfter' => $balanceAfter,
            'message'      => "Payment successful. Balance:{$balanceAfter}",
        ];
    }

    private function activateScheduledVehicle(array $validated, Vehicle $vehicle): array
    {
        // 1. Fetch today's active schedule slot for this specific vehicle directly
        $slot = DailyScheduleSlot::where('schedule_date', today()->toDateString())
            ->where('metadata->assigned_vehicle_id', $vehicle->id)
            ->whereIn('status', ['waiting', 'queued'])
            ->first();

        if (!$slot) {
            return [
                'success' => false,
                'message' => 'No active schedule slot found for this vehicle today.',
            ];
        }

        // 2. Query the live queue record directly using the exact slot ID relation
        $myQueue = Queue::where('plate_number', $vehicle->plate_number)
            ->where('daily_schedule_slot_id', $slot->id)
            ->where('status', 'staging')
            ->lockForUpdate()
            ->first();

        if (!$myQueue) {
            return [
                'success' => false,
                'message' => 'No scheduled staging queue record found for this vehicle, or it is already active.',
            ];
        }

        // 3. Check if another vehicle of the same type is currently loading
        $alreadyLoading = Queue::where('vehicle_type', $vehicle->vehicle_type)
            ->where('status', 'loading')
            ->whereNotNull('daily_schedule_slot_id')
            ->whereHas('dailyScheduleSlot', fn($q) => $q->where('schedule_date', today()->toDateString()))
            ->exists();

        if ($alreadyLoading) {
            return [
                'success' => false,
                'message' => 'Another vehicle is currently loading. Please wait for your turn.',
            ];
        }

        // 4. Check if this vehicle is #1 in the staging queue
        $frontQueue = Queue::where('vehicle_type', $vehicle->vehicle_type)
            ->where('status', 'staging')
            ->whereNotNull('daily_schedule_slot_id')
            ->whereHas('dailyScheduleSlot', fn($q) => $q->where('schedule_date', today()->toDateString()))
            ->orderBy('slot_position', 'asc')
            ->lockForUpdate()
            ->first();

        $isFront = $frontQueue && $frontQueue->id === $myQueue->id;

        if (!$isFront) {
            return [
                'success' => false,
                'message' => "Not your turn yet. You are at position {$myQueue->slot_position}. Please wait.",
            ];
        }

        // 5. Vehicle is #1 — activate and start departure countdown
        $departs_in = match ($vehicle->vehicle_type) {
            'Bus'        => Carbon::now()->addMinutes(1),
            'UV-express' => Carbon::now()->addMinutes(10),
            default      => null,
        };

        $myQueue->update([
            'driver_name' => $validated['driver_name'] ?? $myQueue->driver_name,
            'status'      => 'loading',
            'time_queued' => now(),
            'departs_at'  => $departs_in,
        ]);

        $slot->update(['status' => 'queued']);

        if ($departs_in !== null) {
            ProcessAfterDepart::dispatch($myQueue->id)->delay($departs_in);
        }

        return [
            'success' => true,
            'message' => "Vehicle activated and loading. Departs at {$departs_in?->toTimeString()}",
        ];
    }

    // -------------------------------------------------------------------------
    // Non-scheduled vehicle types (Multi-cab, etc.)
    // -------------------------------------------------------------------------

    private function queueOperatorVehicle(array $validated, Vehicle $vehicle): void
    {
        $user_id = $vehicle->user->id;

        $queueExists = Queue::where('status', 'loading')
            ->where('destination', $validated['destination'])
            ->where('vehicle_type', $vehicle->vehicle_type)
            ->exists();

        if ($queueExists) {
            Queue::create([
                'user_id'       => $user_id,
                'vehicle_type'  => $vehicle->vehicle_type,
                'plate_number'  => $vehicle->plate_number,
                'driver_name'   => $validated['driver_name'],
                'seat_capacity' => $vehicle->total_seats,
                'seat_count'    => 0,
                'time_queued'   => now(),
                'time_departed' => null,
                'destination'   => $validated['destination'],
                'status'        => 'staging',
                'departs_at'    => null,
            ]);
            return;
        }

        $departs_in = match ($vehicle->vehicle_type) {
            'Bus'       => Carbon::now()->addMinutes(15),
            'Multi-cab' => Carbon::now()->addMinutes(2),
            default     => null,
        };

        $queue = Queue::create([
            'user_id'       => $user_id,
            'vehicle_id'    => $vehicle->id,
            'vehicle_type'  => $vehicle->vehicle_type,
            'plate_number'  => $vehicle->plate_number,
            'driver_name'   => $validated['driver_name'],
            'seat_capacity' => $vehicle->total_seats,
            'seat_count'    => 0,
            'time_queued'   => now(),
            'time_departed' => null,
            'destination'   => $validated['destination'],
            'status'        => 'loading',
            'departs_at'    => $departs_in,
        ]);

        if ($departs_in !== null) {
            ProcessAfterDepart::dispatch($queue->id)->delay($queue->departs_at);
        }
    }

    // -------------------------------------------------------------------------
    // Main tap endpoint
    // -------------------------------------------------------------------------

    public function tap(Request $request)
    {
        try {
            $validated = $request->validate([
                'uid'              => 'required|string|max:50',
                'vehicle_id'       => 'required|numeric',
                'name'             => 'nullable|string|max:50',
                'driver_name'      => 'nullable|string|max:100',
                'transaction_type' => 'required|string|max:50',
                'amount'           => 'nullable|numeric|min:0',
                'destination'      => 'nullable|string',
                'vehicle_type'     => 'nullable|string',
                'plate_number'     => 'nullable|string',
            ]);

            Log::info('Card tap received', $validated);

            $card = $this->getCard($validated['uid']);

            if (!$card) {
                return response()->json(['success' => false, 'message' => 'Card not found'], 404);
            }

            if ($card->status !== 'active') {
                return response()->json(['success' => false, 'message' => 'Card is ' . $card->status], 403);
            }

            $balanceBefore    = (float) $card->balance;
            $balanceAfter     = $balanceBefore;
            $status           = 'failed';
            $message          = '';
            $amount           = (float) ($validated['amount'] ?? 0);
            $transaction_type = $validated['transaction_type'];

            if ($transaction_type === 'fare_payment') {

                $isAlreadyInVehicle = TravelRecord::where('user_id', $card->user_id)
                        ->whereHas('queue', function ($query) {
                            $query->where('status', 'loading');
                        })
                        ->exists();

                if ($isAlreadyInVehicle) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot proceed. You are already in the vehicle.'
                    ]);
                }

                $result = DB::transaction(function () use ($validated, $card, $amount, $balanceBefore) {

                    $queue = Queue::where('status', 'loading')
                        ->where('destination', $validated['destination'])
                        ->where('vehicle_type', $validated['vehicle_type'])
                        ->lockForUpdate()
                        ->first();

                    if (!$queue) {
                        return [
                            'success'      => false,
                            'message'      => 'No available vehicle for this destination!',
                            'balanceAfter' => $balanceBefore,
                        ];
                    }

                    $deduction = $this->deductUserCard($card, $amount, $balanceBefore);

                    if ($deduction['success']) {
                        $queue->increment('seat_count');
                        $queue->refresh();

                        TravelRecord::create([
                            'user_id'  => $card->user_id,
                            'queue_id' => $queue->id,
                        ]);

                        if (
                            ($queue->vehicle_type === 'Van' && $queue->seat_count >= 9 && $queue->departs_at === null) || 
                            (($queue->vehicle_type === 'Jeep' && ($queue->destination === 'Buhi' || $queue->destination === 'Mountain-unit')) && $queue->seat_count >= $queue->seat_capacity)
                            ) 
                        {

                            broadcast(new TriggerDepartingEvent($queue->id));
                        }
                    }

                    return $deduction;
                });

                $status       = $result['success'] ? 'success' : 'failed';
                $balanceAfter = $result['balanceAfter'];
                $message      = $result['message'];

                broadcast(new QueuedVehicleEvent());
            }

            if ($transaction_type === 'operator_payment') {
                $vehicle     = $this->getUserVehicle((int) $validated['vehicle_id']);
                $isScheduled = in_array($vehicle->vehicle_type, ['Bus', 'UV-express']);

                if ($isScheduled) {
                   
                    $isGroupActive = DailyScheduleSlot::where('schedule_date', today())
                        ->where('metadata->assigned_vehicle_id', (int) $validated['vehicle_id'])
                        ->whereIn('status', ['waiting', 'queued'])
                        ->exists();

                    if (!$isGroupActive) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active schedule for this vehicle today.',
                        ], 404);
                    }

                  
                    $orderCheck = DB::transaction(function () use ($validated, $card, $amount, $balanceBefore, $vehicle) {
                        $deduction = $this->deductUserCard($card, $amount, $balanceBefore);

                        if (!$deduction['success']) {
                            return $deduction;
                        }

                        $activationResult = $this->activateScheduledVehicle($validated, $vehicle);

                        if (!$activationResult['success']) {
                            // Refund — wrong turn, don't charge
                            $card->update(['balance' => $balanceBefore]);
                            return [
                                'success'      => false,
                                'balanceAfter' => $balanceBefore,
                                'message'      => $activationResult['message'],
                            ];
                        }

                        return [
                            'success'      => true,
                            'balanceAfter' => $deduction['balanceAfter'],
                            'message'      => $activationResult['message'],
                        ];
                    });

                    $status       = $orderCheck['success'] ? 'success' : 'failed';
                    $balanceAfter = $orderCheck['balanceAfter'];
                    $message      = $orderCheck['message'];
                } else {
                    $alreadyInQueue = $this->isVehicleAlreadyQueued($validated['plate_number']);

                    if ($alreadyInQueue) {
                        return response()->json([
                            'success'      => false,
                            'balanceAfter' => $balanceBefore,
                            'message'      => 'Vehicle is already in queue',
                        ]);
                    }

                    $result = $this->deductUserCard($card, $amount, $balanceBefore);

                    if ($result['success']) {
                        $this->queueOperatorVehicle($validated, $vehicle);
                    }

                    $status       = $result['success'] ? 'success' : 'failed';
                    $balanceAfter = $result['balanceAfter'];
                    $message      = $result['message'];
                }

                broadcast(new QueuedVehicleEvent());
            }

            // ------------------------------------------------------------------
            // Record transaction
            // ------------------------------------------------------------------
            $transaction = CardTransaction::create([
                'card_id'          => $card->id,
                'points_deducted'  => -$amount,
                'transaction_type' => $transaction_type,
                'amount'           => $amount,
                'balance_before'   => $balanceBefore,
                'balance_after'    => $balanceAfter,
                'status'           => $status,
                'message'          => $message,
                'transaction_time' => now(),
            ]);

            return response()->json([
                'success'          => $status === 'success',
                'message'          => $message,
                'transaction_type' => $transaction_type,
                'card_holder'      => $card->user->name,
                'card_type'        => $card->user->role,
                'balance_before'   => $balanceBefore,
                'balance_after'    => (float) $balanceAfter,
                'transaction_id'   => $transaction->id,
                'timestamp'        => $transaction->transaction_time->toIso8601String(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Card tap error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 499) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}