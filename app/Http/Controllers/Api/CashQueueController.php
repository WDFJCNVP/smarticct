<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

use App\Models\Queue;
use App\Models\Vehicle;
use App\Models\DailyScheduleSlot;
use App\Models\CashTransaction;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Jobs\ProcessAfterDepart;
use App\Events\QueuedVehicleEvent;
use App\Events\NotificationEvent;
use App\Services\AuditLogsService;

class CashQueueController extends Controller
{
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

    private function notifyQueueJoined(int $userId, string $vehicleType, string $plateNumber, string $message): void
    {
        $notification = Notification::create([
            'type'     => 'Queued',
            'title'    => 'Vehicle Queued',
            'message'  => $message,
            'metadata' => json_encode(['plate_number' => $plateNumber, 'vehicle_type' => $vehicleType]),
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $userId,
        ]);

        broadcast(new NotificationEvent());
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

        $departsIn = match ($vehicle->vehicle_type) {
            'Bus'        => Carbon::now()->addMinutes(30),
            'UV-express' => null,
            default      => null,
        };

        $myQueue->update([
            'driver_name' => $validated['driver_name'] ?? $myQueue->driver_name,
            'status'      => 'loading',
            'time_queued' => now(),
            'departs_at'  => $departsIn,
        ]);

        $slot->update(['status' => 'queued']);

        if ($departsIn !== null) {
            ProcessAfterDepart::dispatch($myQueue->id)->delay($departsIn);
        }

        $this->notifyQueueJoined(
            $vehicle->user->id,
            $vehicle->vehicle_type,
            $vehicle->plate_number,
            "Your {$vehicle->vehicle_type} with plate number {$vehicle->plate_number} has joined the queue and is now accepting passengers."
        );

        return [
            'success' => true,
            'message' => "Vehicle successfully activated and is now accepting passengers. Scheduled departure: {$departsIn?->format('h:i A')}.",
            'queue'   => $myQueue,
        ];
    }

    private function queueOperatorVehicle(array $validated, Vehicle $vehicle): Queue
    {
        $userId = (int) ($validated['operator_id'] ?? $vehicle->user->id);

        $queueExists = Queue::where('status', 'loading')
            ->where('destination', $validated['destination'])
            ->where('vehicle_type', $vehicle->vehicle_type)
            ->exists();

        if ($queueExists) {
            $queue = Queue::create([
                'user_id'       => $userId,
                'vehicle_id'    => $vehicle->id,
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
                'slot_position' => Queue::where('destination', $validated['destination'])
                    ->whereIn('status', ['staging', 'loading'])
                    ->max('slot_position') + 1,
            ]);

            $this->notifyQueueJoined(
                $userId,
                $vehicle->vehicle_type,
                $vehicle->plate_number,
                "Your {$vehicle->vehicle_type} with plate number {$vehicle->plate_number} has joined the queue for {$validated['destination']}. You'll be notified when it's your turn to load."
            );

            return $queue;
        }

        Log::info('Queuing vehicle type via cash: [' . $vehicle->vehicle_type . ']');

        $departsIn = match ($vehicle->vehicle_type) {
            'Bus'       => Carbon::now()->addMinutes(30),
            'Multi-cab' => Carbon::now()->addMinutes(2),
            'Jeep'      => !in_array($validated['destination'], ['Buhi', 'Mountain-unit']) ? Carbon::now()->addMinutes(30) : null,
            default     => null,
        };

        $queue = Queue::create([
            'user_id'       => $userId,
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
            'departs_at'    => $departsIn,
            'slot_position' => 1,
        ]);

        if ($departsIn !== null) {
            ProcessAfterDepart::dispatch($queue->id)->delay($queue->departs_at);
        }

        $this->notifyQueueJoined(
            $userId,
            $vehicle->vehicle_type,
            $vehicle->plate_number,
            "Your {$vehicle->vehicle_type} with plate number {$vehicle->plate_number} has joined the queue and is now accepting passengers."
        );

        return $queue;
    }

    public function queue(Request $request)
    {
        try {
            $validated = $request->validate([
                'operator_id'      => 'required|numeric|exists:users,id',
                'vehicle_id'       => 'required|numeric|exists:vehicles,id',
                'driver_name'      => 'nullable|string|max:100',
                'amount'           => 'required|numeric|min:0',
                'amount_received'  => 'required|numeric|min:0',
                'change'           => 'nullable|numeric|min:0',
                'destination'      => 'required|string',
                'vehicle_type'     => 'required|string',
                'plate_number'     => 'required|string',
            ]);

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

                $orderCheck = DB::transaction(function () use ($validated, $vehicle) {
                    $activationResult = $this->activateScheduledVehicle($validated, $vehicle);

                    if (!$activationResult['success']) {
                        return [
                            'success' => false,
                            'message' => $activationResult['message'],
                        ];
                    }

                    $queue = $activationResult['queue'];

                    CashTransaction::create([
                        'processed_by'    => auth()->id(),
                        'operator_id'     => $validated['operator_id'],
                        'vehicle_id'      => $vehicle->id,
                        'queue_id'        => $queue->id,
                        'amount'          => $validated['amount'],
                        'amount_received' => $validated['amount_received'],
                        'change'          => $validated['change'] ?? max(0, $validated['amount_received'] - $validated['amount']),
                        'reference_no'    => 'CASH-' . now()->format('YmdHis') . '-' . $queue->id,
                        'notes'           => 'Cash payment for queueing vehicle #' . $queue->id,
                        'status'          => 'success',
                    ]);

                    app(AuditLogsService::class)->create([
                        'user_id'  => auth()->id(),
                        'action'   => 'Queued Vehicle',
                        'subject'  => 'Vehicle Queued Successfully (Cash)',
                        'channel'  => 'Web',
                        'metadata' => [
                            'ip_address'   => request()->ip(),
                            'payment_mode' => 'cash',
                            'queue_id'     => $queue->id,
                            'message'      => "Vehicle queued (ID: {$queue->id}) for vehicle: {$vehicle->plate_number}",
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => $activationResult['message'],
                    ];
                });

                if (!$orderCheck['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $orderCheck['message'],
                    ], 400);
                }

                $message = $orderCheck['message'];

            } else {
                $alreadyInQueue = $this->isVehicleAlreadyQueued($validated['plate_number']);

                if ($alreadyInQueue) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This vehicle is already queued or currently loading.',
                    ]);
                }

                $result = DB::transaction(function () use ($validated, $vehicle) {
                    $queue = $this->queueOperatorVehicle($validated, $vehicle);

                    CashTransaction::create([
                        'processed_by'    => auth()->id(),
                        'operator_id'     => $validated['operator_id'],
                        'vehicle_id'      => $vehicle->id,
                        'queue_id'        => $queue->id,
                        'amount'          => $validated['amount'],
                        'amount_received' => $validated['amount_received'],
                        'change'          => $validated['change'] ?? max(0, $validated['amount_received'] - $validated['amount']),
                        'reference_no'    => 'CASH-' . now()->format('YmdHis') . '-' . $queue->id,
                        'notes'           => 'Cash payment for queueing vehicle #' . $queue->id,
                        'status'          => 'success',
                    ]);

                    app(AuditLogsService::class)->create([
                        'user_id'  => auth()->id(),
                        'action'   => 'Queued Vehicle',
                        'subject'  => 'Vehicle Queued Successfully (Cash)',
                        'channel'  => 'Web',
                        'metadata' => [
                            'ip_address'   => request()->ip(),
                            'payment_mode' => 'cash',
                            'queue_id'     => $queue->id,
                            'message'      => "Vehicle queued (ID: {$queue->id}) for vehicle: {$vehicle->plate_number}",
                        ],
                    ]);

                    return [
                        'success' => true,
                        'message' => "Vehicle {$vehicle->plate_number} queued successfully via cash payment.",
                    ];
                });

                $message = $result['message'];
            }

            broadcast(new QueuedVehicleEvent());

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cash queue error: ' . $e->getMessage());
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 499) ? $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}