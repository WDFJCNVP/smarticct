<?php

namespace App\Services;

use App\Models\User;
use App\Models\VehicleGroup;
use App\Models\RouteList;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use App\Services\QueueManagementService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{

    private function getAdmin() {
        return User::where('role', 'admin')->first();
    }

    public function create( array $userBasicInformation, array $cardInformation, ?array $vehicles = null,): User {

        return DB::transaction(function () use ($userBasicInformation, $cardInformation, $vehicles) {

            $userBasicInformation['password'] = Hash::make($userBasicInformation['password']);

            $user = User::create($userBasicInformation);
            $user->card()->create($cardInformation);    

            if($this->getAdmin()) {
                $admin = $this->getAdmin();
                $notification = Notification::create([
                    'type'    => 'Registration',
                    'title'   => 'User Registration',
                    'message' => "You have successfully registered a new user. You can manage and monitor the user's details on the Users page. User ID: {$user->user_code}"
                ]);

                UserNotification::create([
                    'notification_id' => $notification->id,
                    'user_id' => $admin->id,
                ]);

            }

            broadcast(new NotificationEvent());

            if ($userBasicInformation['role'] === 'operator' && !empty($vehicles)) {
                foreach ($vehicles as $vehicle) {

                    $route_list = RouteList::where('vehicle_type', $vehicle['vehicle_type'])->where('terminal', $vehicle['route'])->first();

                    $created_vehicle = $user->vehicles()->create([
                        'route_list_id'    => $route_list->id,
                        'vehicle_type'     => $vehicle['vehicle_type'],
                        'plate_number'     => $vehicle['plate_number'],
                        'total_seats'      => $vehicle['seat_capacity'],
                        'official_record'  => $vehicle['official_record'],
                    ]);

                    if ($vehicle['group_number'] !== null) {
                        $order_number = VehicleGroup::where('group_number', $vehicle['group_number'])
                            ->whereHas('vehicle', function($query) use ($created_vehicle) {
                            $query->where('vehicle_type', $created_vehicle->vehicle_type);
                        })->max('order_number') + 1;

                        $created_vehicle->vehicle_group()->create([
                            'group_number' => (int) $vehicle['group_number'],
                            'order_number' => $order_number,
                        ]);
                    }
                }
            }
            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);
            return $user;
        });
    }

    public function destroy(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->delete();
        });
    }
}