<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Queue;
use App\Models\Vehicle;
use App\Models\DailyScheduleSlot;
use App\Models\RouteList;
use App\Models\TravelRecord;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Jobs\ProcessAfterDepart;
use App\Events\QueuedVehicleEvent;
use App\Events\TriggerDepartingEvent;
use App\Events\NotificationEvent;
use App\Events\CardTransactionCreated;
use App\Events\CashTransactionCreated;

use App\Services\AuditLogsService;

class CardController extends Controller
{
    private $travel_record;

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

    private function notifyQueueJoined(int $user_id, string $vehicleType, string $plateNumber, string $message): void
    {
        $notification = Notification::create([
            'type'    => 'Queued',
            'title'   => 'Vehicle Queued',
            'message' => $message,
            'metadata' => json_encode(['plate_number' => $plateNumber, 'vehicle_type' => $vehicleType]),
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $user_id,
        ]);

        try {
            broadcast(new NotificationEvent());
        } catch (\Exception $e) {
            Log::warning('Queue-joined notification created but broadcast failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify a commuter that a fare was deducted from their card. Mirrors
     * the notification pattern already used for top-ups/withdrawals — the
     * fare_payment flow previously created CardTransaction rows silently,
     * so nothing ever reached the commuter's notification bell.
     */
    private function notifyFareDeducted(int $user_id, float $amount, string $destination, float $balanceAfter): void
    {
        $notification = Notification::create([
            'type'    => 'FarePayment',
            'title'   => 'Fare paid',
            'message' => "₱" . number_format($amount, 2) . " was deducted for your trip to {$destination}. Remaining balance: ₱" . number_format($balanceAfter, 2) . ".",
            'metadata' => json_encode(['amount' => $amount, 'destination' => $destination, 'balance_after' => $balanceAfter]),
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $user_id,
        ]);
    }

    private function deductUserCard(Card $card, float $amount, float $balanceBefore): array
    {
        if ($balanceBefore < $amount) {
            return [
                'success'      => false,
                'balanceAfter' => $balanceBefore,
                'message'      => "Insufficient balance. Available balance: $balanceBefore points. Required amount: $amount points.",
            ];
        }

        $balanceAfter = DB::transaction(function () use ($card, $amount) {
            $card = Card::lockForUpdate()->findOrFail($card->id);
            $balanceAfter = (float) $card->balance - $amount;
            $card->update(['balance' => $balanceAfter, 'updated_at' => now()]);
            return $balanceAfter;
        });

        return [
            'success'      => true,
            'balanceAfter' => $balanceAfter,
            'message'      => "Payment successful. Remaining balance: $balanceAfter points.",
        ];
    }

    private function activateScheduledVehicle(array $validated, Vehicle $vehicle): array
    {
       
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

        
        $alreadyLoading = Queue::where('vehicle_type', $vehicle->vehicle_type)
            ->where('status', 'loading')
            ->whereNotNull('daily_schedule_slot_id')
            ->whereHas('dailyScheduleSlot', fn($q) => $q->where('schedule_date', today()->toDateString()))
            ->exists();

        if ($alreadyLoading) {
            return [
                'success' => false,
                'message' => 'Another vehicle of the same type is currently loading. Please wait until it departs.',
            ];
        }

        
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
                'message' => "Queue activation denied. Your vehicle is currently at position $myQueue->slot_position. Please wait for your turn.",
            ];
        }

        
        $departs_in = match ($vehicle->vehicle_type) {
            'Bus'        => Carbon::now()->addMinutes(30),
            'UV-express' => null,
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

        $this->notifyQueueJoined(
            $vehicle->user->id,
            $vehicle->vehicle_type,
            $vehicle->plate_number,
            "Your {$vehicle->vehicle_type} with plate number {$vehicle->plate_number} has joined the queue and is now accepting passengers."
        );

        return [
            'success' => true,
            'message' => "Vehicle successfully activated and is now accepting passengers. Scheduled departure: {$departs_in?->format('h:i A')}.",
            'queue'   => $myQueue,
        ];
    }

    // Non-scheduled vehicle types (Multi-cab, etc.)

    private function queueOperatorVehicle(array $validated, Vehicle $vehicle): Queue
    {
        $user_id = $vehicle->user->id;

        $queueExists = Queue::where('status', 'loading')
            ->where('destination', $validated['destination'])
            ->where('vehicle_type', $vehicle->vehicle_type)
            ->exists();

        if ($queueExists) {
            $queue = Queue::create([
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

            $this->notifyQueueJoined(
                $user_id,
                $vehicle->vehicle_type,
                $vehicle->plate_number,
                "Your {$vehicle->vehicle_type} with plate number {$vehicle->plate_number} has joined the queue for {$validated['destination']}. You'll be notified when it's your turn to load."
            );

            return $queue;
        }

        Log::info('Queuing vehicle type: [' . $vehicle->vehicle_type . ']');

        $departs_in = match ($vehicle->vehicle_type) {
            'Bus'       => Carbon::now()->addMinutes(30),
            'Multi-cab' => Carbon::now()->addMinutes(2),
            'Jeep'      => !in_array($validated['destination'], ['Buhi', 'Mountain-unit']) ? Carbon::now()->addMinutes(30) : null,
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

        $this->notifyQueueJoined(
            $user_id,
            $vehicle->vehicle_type,
            $vehicle->plate_number,
            "Your {$vehicle->vehicle_type} with plate number {$vehicle->plate_number} has joined the queue and is now accepting passengers."
        );

        return $queue;
    }

    // Cash fare payment (no card involved for the commuter side).
    // Mirrors the fare_payment branch of tap(), minus the card deduction,
    // and records a CashTransaction instead of a queue_deduction CardTransaction.

    public function cashFarePayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'destination'     => 'required|string',
                'vehicle_type'    => 'required|string',
                'amount'          => 'required|numeric|min:0.01',
                'amount_received' => 'required|numeric|min:0',
                'user_id'         => 'nullable|integer|exists:users,id',
            ]);

            if ((float) $validated['amount_received'] < (float) $validated['amount']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amount received is less than the fare.',
                ], 422);
            }

            $result = DB::transaction(function () use ($validated) {
                $queue = Queue::with('user.card')->where('status', 'loading')
                    ->where('destination', $validated['destination'])
                    ->where('vehicle_type', $validated['vehicle_type'])
                    ->lockForUpdate()
                    ->first();

                if (!$queue) {
                    return [
                        'success' => false,
                        'message' => 'No loading vehicle is currently available for the selected destination.',
                    ];
                }

                if ($queue->seat_count >= $queue->seat_capacity) {
                    return [
                        'success' => false,
                        'message' => 'This vehicle is already full.',
                    ];
                }

                $amount = (float) $validated['amount'];

                $queue->increment('seat_count');
                $queue->refresh();

                $rider = !empty($validated['user_id']) ? \App\Models\User::find($validated['user_id']) : null;

                $travelRecord = TravelRecord::create([
                    'user_id'      => $rider?->id,
                    'queue_id'     => $queue->id,
                    'destination'  => $queue->destination,
                    'vehicle_type' => $queue->vehicle_type,
                    'plate_number' => $queue->plate_number,
                    'driver_name'  => $queue->driver_name,
                    'user_type'    => $rider?->commuter_type ?? 'walk-in',
                    'amount'       => $amount,
                    'departed_at'  => $queue->departs_at,
                ]);

                // Cash fare: the operator already holds this money physically —
                // it is a separate, non-withdrawable earning that must never touch
                // the operator's digital card balance. No CardTransaction is
                // created here; the CashTransaction below is the sole record
                // (used by the operator dashboard's todayEarnings() cash-side query).

                $vehicleId = $queue->vehicle_id
                    ?? Vehicle::where('plate_number', $queue->plate_number)->value('id');

                if ($vehicleId) {
                    \App\Models\CashTransaction::create([
                        'processed_by'     => auth()->id(),
                        'operator_id'      => $queue->user_id,
                        'vehicle_id'       => $vehicleId,
                        'queue_id'         => $queue->id,
                        'amount'           => $amount,
                        'amount_received'  => $validated['amount_received'],
                        'change'           => max(0, (float) $validated['amount_received'] - $amount),
                        'reference_no'     => 'FARECASH-' . now()->format('YmdHis') . '-' . $queue->id,
                        'notes'            => "Cash fare payment for {$queue->destination} trip, plate {$queue->plate_number}",
                        'status'           => 'success',
                    ]);
                }

                // Same auto-departure rules as the card fare_payment flow.
                if ($queue->vehicle_type === 'UV-express' && $queue->seat_count >= 9 && $queue->departs_at === null) {
                    $departsAt = Carbon::now()->addMinutes(30);
                    $queue->update(['departs_at' => $departsAt]);

                    ProcessAfterDepart::dispatch($queue->id)->delay($departsAt);
                } elseif ($queue->seat_count >= $queue->seat_capacity) {
                    $queue->update(['departs_at' => Carbon::now()]);

                    ProcessAfterDepart::dispatch($queue->id);
                }

                return [
                    'success'        => true,
                    'message'        => 'Cash fare payment successful.',
                    'change'         => max(0, (float) $validated['amount_received'] - $amount),
                    'seat_count'     => $queue->seat_count,
                    'seat_capacity'  => $queue->seat_capacity,
                    'plate_number'   => $queue->plate_number,
                    'destination'    => $queue->destination,
                ];
            });

            if ($result['success']) {
                broadcast(new QueuedVehicleEvent());

                try {
                    broadcast(new CardTransactionCreated(transactionType: 'fare_earning'));
                    broadcast(new CashTransactionCreated());
                } catch (\Exception $e) {
                    Log::warning('Cash fare payment succeeded but broadcast failed', ['error' => $e->getMessage()]);
                }
            }

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cash fare payment error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 499) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    // Operator self-reported cash fare (commuter pays the operator directly,
    // no cashier and no card involved). Only increments seat_count and logs
    // the trip/cash record — never touches the operator's card balance,
    // since the operator is already physically holding that cash.

    public function operatorCashFare(Request $request)
    {
        try {
            $validated = $request->validate([
                'queue_id' => 'required|integer|exists:queues,id',
            ]);

            $result = DB::transaction(function () use ($validated) {
                $queue = Queue::where('id', $validated['queue_id'])
                    ->where('status', 'loading')
                    ->lockForUpdate()
                    ->first();

                if (!$queue) {
                    return [
                        'success' => false,
                        'message' => 'This vehicle is not currently boarding.',
                    ];
                }

                if ($queue->user_id !== auth()->id()) {
                    return [
                        'success' => false,
                        'message' => 'You can only log passengers for your own vehicle.',
                    ];
                }

                if ($queue->seat_count >= $queue->seat_capacity) {
                    return [
                        'success' => false,
                        'message' => 'This vehicle is already full.',
                    ];
                }

                $routeList = RouteList::where('terminal', $queue->destination)
                    ->whereHas('operatorTicketRate', fn ($q) => $q->where('vehicle_type', $queue->vehicle_type))
                    ->first();

                $amount = (float) ($routeList->fare ?? 0);

                $queue->increment('seat_count');
                $queue->refresh();

                $travelRecord = TravelRecord::create([
                    'user_id'      => null,
                    'queue_id'     => $queue->id,
                    'destination'  => $queue->destination,
                    'vehicle_type' => $queue->vehicle_type,
                    'plate_number' => $queue->plate_number,
                    'driver_name'  => $queue->driver_name,
                    'user_type'    => 'walk-in',
                    'amount'       => $amount,
                    'departed_at'  => $queue->departs_at,
                ]);

                $vehicleId = $queue->vehicle_id
                    ?? Vehicle::where('plate_number', $queue->plate_number)->value('id');

                if ($vehicleId) {
                    \App\Models\CashTransaction::create([
                        'processed_by'     => auth()->id(),
                        'operator_id'      => $queue->user_id,
                        'vehicle_id'       => $vehicleId,
                        'queue_id'         => $queue->id,
                        'amount'           => $amount,
                        'amount_received'  => $amount,
                        'change'           => 0,
                        'reference_no'     => 'OPFARE-' . now()->format('YmdHis') . '-' . $queue->id,
                        'notes'            => "Cash fare paid directly to the operator (self-logged), {$queue->destination} trip, plate {$queue->plate_number}.",
                        'status'           => 'success',
                    ]);
                }

                // Same auto-departure rules as the other fare-payment flows.
                if ($queue->vehicle_type === 'UV-express' && $queue->seat_count >= 9 && $queue->departs_at === null) {
                    $departsAt = Carbon::now()->addMinutes(30);
                    $queue->update(['departs_at' => $departsAt]);

                    ProcessAfterDepart::dispatch($queue->id)->delay($departsAt);
                } elseif ($queue->seat_count >= $queue->seat_capacity) {
                    $queue->update(['departs_at' => Carbon::now()]);

                    ProcessAfterDepart::dispatch($queue->id);
                }

                return [
                    'success'       => true,
                    'message'       => "Passenger logged. Seats: {$queue->seat_count}/{$queue->seat_capacity}.",
                    'seat_count'    => $queue->seat_count,
                    'seat_capacity' => $queue->seat_capacity,
                ];
            });

            if ($result['success']) {
                broadcast(new QueuedVehicleEvent());

                try {
                    broadcast(new CashTransactionCreated());
                } catch (\Exception $e) {
                    Log::warning('Operator cash fare succeeded but broadcast failed', ['error' => $e->getMessage()]);
                }
            }

            return response()->json($result);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Operator self-reported cash fare error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 499) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    // Main tap endpoint

    public function tap(Request $request)
    {
        try {
            $validated = $request->validate([
                'uid'              => ['required', 'string', 'regex:/^[0-9A-Fa-f]{8}$/'],
                'vehicle_id'       => 'nullable|numeric',
                'name'             => 'nullable|string|max:50',
                'driver_name'      => 'required_if:transaction_type,operator_payment|nullable|string|max:100',
                'transaction_type' => [
                                        'required',
                                        Rule::in([
                                            'fare_payment',
                                            'operator_payment',
                                            'top_up'
                                        ])
                                    ],
                'amount'           => 'nullable|numeric|min:0',
                'destination'      => 'nullable|string',
                'vehicle_type'     => 'nullable|string',
                'plate_number'     => 'nullable|string',
            ]);

            // dd($validated['vehicle_type']);

            $validated['uid'] = strtoupper($validated['uid']);

            Log::info('Card tap received', $validated);

            $card = $this->getCard($validated['uid']);

            // Kiosk hits this endpoint unauthenticated, so auth()->id() is
            // always null for kiosk-originated taps — 'channel' reflects
            // that this request never goes through an authenticated web
            // session, unlike the cashier-facing endpoints.
            $auditChannel = auth()->id() ? 'Web' : 'Kiosk';

            if (!$card) {
                app(AuditLogsService::class)->create([
                    'user_id' => auth()->id(),
                    'action'  => 'Tap Card',
                    'subject' => 'Tapped Card Not Recognized',
                    'channel' => $auditChannel,
                    'metadata' => [
                        'ip_address' => request()->ip(),
                        'message'    => "Card was not recognized (Card ID: {$validated['uid']}).",
                    ],
                ]);

                return response()->json(['success' => false, 'message' => 'Card not recognized. Please contact an administrator if the problem persists.'], 404);
            }

            if ($card->status !== 'active') {
                app(AuditLogsService::class)->create([
                    'user_id' => auth()->id(),
                    'action'  => 'Tap Card',
                    'subject' => 'Tapped Card Inactive',
                    'channel' => $auditChannel,
                    'metadata' => [
                        'ip_address' => request()->ip(),
                        'message'    => "Card is inactive (Card Number: {$card->card_number}). Card status: {$card->status} (User No.: {$card->user->user_code}).",
                    ],
                ]);

                return response()->json(['success' => false, 'message' => 'Card is ' . $card->status], 403);
            }

            app(AuditLogsService::class)->create([
                'user_id' => auth()->id(),
                'action'  => 'Tap Card',
                'subject' => 'User tapped card',
                'channel' => $auditChannel,
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => "Card was successfully tapped (Card ID: {$card->card_number} User No.: {$card->user->user_code}).",
                ],
            ]);

            $balanceBefore    = (float) $card->balance;
            $balanceAfter     = $balanceBefore;
            $status           = 'failed';
            $message          = '';
            $amount           = (float) ($validated['amount'] ?? 0);
            $transaction_type = $validated['transaction_type'];

            if ($transaction_type === 'fare_payment') {

                $result = DB::transaction(function () use ($validated, $card, $amount, $balanceBefore) {
                    $queue = Queue::with('user.card')->where('status', 'loading')
                        ->where('destination', $validated['destination'])
                        ->where('vehicle_type', $validated['vehicle_type'])
                        ->lockForUpdate()
                        ->first();

                    if (!$queue) {
                        return [
                            'success'      => false,
                            'message'      => 'No loading vehicle is currently available for the selected destination.',
                            'balanceAfter' => $balanceBefore,
                        ];
                    }

                    $isAlreadyInVehicle = TravelRecord::where('user_id', $card->user_id)
                        ->whereHas('queue', function ($query) {
                            $query->where('status', 'loading');
                        })
                        ->exists();

                    if ($isAlreadyInVehicle) {
                        return [
                            'success'      => false,
                            'balanceAfter' => $balanceBefore,
                            'message'      => 'Boarding denied. You already have an active trip in a loading vehicle.',
                        ];
                    }

                    $deduction = $this->deductUserCard($card, $amount, $balanceBefore);

                    if ($deduction['success']) {
                        $queue->increment('seat_count');
                        $queue->refresh();

                        $operatorCard = $queue->user->card;
                        $operatorBalanceBefore = $operatorCard->balance;

                        $this->travel_record = TravelRecord::create([
                            'user_id'       => $card->user_id,
                            'queue_id'      => $queue->id,
                            'destination'   => $queue->destination,
                            'vehicle_type'  => $queue->vehicle_type,
                            'plate_number'  => $queue->plate_number,
                            'driver_name'   => $queue->driver_name,
                            'user_type'     => $card->user->commuter_type,
                            'amount'        => $amount,
                            'departed_at'   => $queue->departs_at,
                        ]);

                        $operatorCard->increment('balance', $amount);
                        $operatorCard->refresh();

                        //Create CardTransaction (operator record)
                        CardTransaction::create([
                            'card_id'          => $operatorCard->id,
                            'processed_by'     => auth()->id(),
                            'transaction_type' => 'fare_earning',
                            'reference_no'     => 'FARE-' . $this->travel_record->id,
                            'reference_id'     => $this->travel_record->id,
                            'reference_type'   => TravelRecord::class,
                            'amount'           => $amount,
                            'balance_before'   => $operatorBalanceBefore,
                            'balance_after'    => $operatorCard->balance,
                            'status'           => 'success',
                            'transaction_time' => now(),
                            'source'           => 'kiosk_tap_in',
                            'message'          => "Fare earning: {$queue->destination} trip, plate {$queue->plate_number}",
                        ]);

                        CardTransaction::create([
                            'card_id'          => $card->id,
                            'processed_by'     => auth()->id(),
                            'transaction_type' => 'queue_deduction',
                            'reference_no'     => 'TAPIN-' . $this->travel_record->id,
                            'reference_id'     => $this->travel_record->id,
                            'reference_type'   => TravelRecord::class,
                            'amount'           => $amount,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $deduction['balanceAfter'],
                            'status'           => 'success',
                            'transaction_time' => now(),
                            'source'           => 'kiosk_tap_in',
                            'message'          => "Fare paid: {$queue->destination} trip, plate {$queue->plate_number}",
                        ]);

                        // Let the commuter's notification bell / dashboard know
                        // a fare was just deducted — previously silent.
                        $this->notifyFareDeducted($card->user_id, $amount, $queue->destination, $deduction['balanceAfter']);

                        if ($queue->vehicle_type === 'UV-express' && $queue->seat_count >= 9 && $queue->departs_at === null) {
                            $departsAt = Carbon::now()->addMinutes(30);
                            $queue->update(['departs_at' => $departsAt]);

                            ProcessAfterDepart::dispatch($queue->id)->delay($departsAt);
                        }
                        elseif ($queue->seat_count >= $queue->seat_capacity) {
                            $queue->update(['departs_at' => Carbon::now()]);

                            ProcessAfterDepart::dispatch($queue->id);
                        }
                    }

                    return $deduction;
                });

                $status       = $result['success'] ? 'success' : 'failed';
                $balanceAfter = $result['balanceAfter'];
                $message      = $result['message'];

                if ($status === 'success') {
                    broadcast(new QueuedVehicleEvent());

                    try {
                        broadcast(new NotificationEvent());
                        broadcast(new CardTransactionCreated(cardId: $card->id, transactionType: 'queue_deduction'));
                    } catch (\Exception $e) {
                        Log::warning('Fare payment succeeded but broadcast failed', ['error' => $e->getMessage()]);
                    }
                }
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
                            'message' => 'Your turn has passed for this round. Please wait for the rest of your group to depart before the next round begins.',
                        ], 404);
                    }

                  
                    $orderCheck = DB::transaction(function () use ($validated, $card, $amount, $balanceBefore, $vehicle) {
                        $deduction = $this->deductUserCard($card, $amount, $balanceBefore);

                        if (!$deduction['success']) {
                            return $deduction;
                        }

                        $activationResult = $this->activateScheduledVehicle($validated, $vehicle);

                        if (!$activationResult['success']) {
                            $card->update(['balance' => $balanceBefore]);
                            return [
                                'success'      => false,
                                'balanceAfter' => $balanceBefore,
                                'message'      => $activationResult['message'],
                            ];
                        }

                        CardTransaction::create([
                            'card_id'          => $card->id,
                            'processed_by'     => auth()->id(),
                            'transaction_type' => 'queueing_fee',
                            'reference_no'     => 'QFEE-' . now()->timestamp . '-' . $card->id,
                            'reference_id'     => $activationResult['queue']->id,
                            'reference_type'   => Queue::class,
                            'amount'           => $amount,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $deduction['balanceAfter'],
                            'status'           => 'success',
                            'transaction_time' => now(),
                            'source'           => 'kiosk_tap_in',
                            'message'          => "Queueing fee for {$vehicle->vehicle_type}, plate {$vehicle->plate_number}",
                        ]);

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
                            'message'      => 'This vehicle is already queued or currently loading.',
                        ]);
                    }

                    $result = DB::transaction(function () use ($validated, $card, $amount, $balanceBefore, $vehicle) {

                        $deduction = $this->deductUserCard($card, $amount, $balanceBefore);

                        if ($deduction['success']) {

                            $queue = $this->queueOperatorVehicle($validated, $vehicle);

                            CardTransaction::create([
                                'card_id'          => $card->id,
                                'processed_by'     => auth()->id(), // the cashier operating this counter/session
                                'transaction_type' => 'queueing_fee',
                                'reference_no'     => 'QFEE-' . now()->timestamp . '-' . $card->id,
                                'reference_id'     => $queue->id,
                                'reference_type'   => Queue::class,
                                'amount'           => $amount,
                                'balance_before'   => $balanceBefore,
                                'balance_after'    => $deduction['balanceAfter'],
                                'status'           => 'success',
                                'transaction_time' => now(),
                                'source'           => 'kiosk_tap_in',
                                'message'          => "Queueing fee for {$vehicle->vehicle_type}, plate {$vehicle->plate_number}",
                            ]);
                        }

                        return $deduction;
                    });

                    $status       = $result['success'] ? 'success' : 'failed';
                    $balanceAfter = $result['balanceAfter'];
                    $message      = $result['message'];
                }

                if ($status === 'success') {
                    broadcast(new QueuedVehicleEvent());

                    try {
                        broadcast(new CardTransactionCreated(cardId: $card->id, transactionType: 'queueing_fee'));
                    } catch (\Exception $e) {
                        Log::warning('Operator queueing fee succeeded but broadcast failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            return response()->json([
                'success'          => $status === 'success',
                'message'          => $message,
                'transaction_type' => $transaction_type,
                'card_holder'      => $card->user->name,
                'card_type'        => $card->user->role,
                'balance_before'   => $balanceBefore,
                'balance_after'    => (float) $balanceAfter,
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