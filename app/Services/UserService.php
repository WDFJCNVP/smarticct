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
        return User::where('role', 'admin')->get();
    }

    public function create( array $userBasicInformation, array $cardInformation, ?array $vehicles = null,): User {

        return DB::transaction(function () use ($userBasicInformation, $cardInformation, $vehicles) {

            $userBasicInformation['password'] = Hash::make($userBasicInformation['password']);

            $user = User::create($userBasicInformation);
            $user->card()->create($cardInformation);    

            foreach($this->getAdmin() as $admin) {

                $notification = Notification::create([
                    'type'    => 'operator_registration',
                    'title'   => 'User Registration',
                    'message' => "You've successfully register new operator. You can manage and monitor its details in the Users page. User id:$user->user_code"
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
                        'route_list_id' => $route_list->id,
                        'vehicle_type' => $vehicle['vehicle_type'],
                        'plate_number' => $vehicle['plate_number'],
                        'total_seats'  => 10,
                    ]);

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