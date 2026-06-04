<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Events\UserInfoUpdated;
use Livewire\Attributes\On;

use App\Models\Vehicle;
use App\Models\User;

new #[Layout('layouts.dashboard.operator.operator-dashboard')]class extends Component
{

    #[Computed]
    public function vehicles() {
        return Vehicle::with('user','route.terminal')
            ->where('user_id', auth()->user()->id)
            ->get();
    }

    // #[Computed]
    // public function getUser() {
    //     return User::where('id', auth()->user()->id)->first();
    // }


    #[On('echo:user-info-updated,UserInfoUpdated')]
    public function refreshUserInfo() {

        unset($this->vehicles);
    }
}
?>

<div>
    <x-pages-heading 
        :count="$this->vehicles->count()" 
        heading="My Vehicles" 
        description="You can monitor your vehicles and it's status here."
        >

        All Vehicles
    </x-pages-heading>

    <div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Plate Number</flux:table.column>
                <flux:table.column>Vehicle Type</flux:table.column>
                <flux:table.column>Route</flux:table.column>
                <flux:table.column>Date Registered</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>

                @foreach ($this->vehicles as $vehicle)

                    <flux:table.row>
                        <flux:table.cell>{{$vehicle['plate_number']}}</flux:table.cell>
                        <flux:table.cell>{{$vehicle->vehicle_type}}</flux:table.cell>
                        <flux:table.cell>Iriga Terminal to {{$vehicle->route->terminal->municipality}}</flux:table.cell>
                        <flux:table.cell>{{ $vehicle->created_at->format('M d, Y') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            @if ($vehicle->status === 'loading')
                                <flux:badge color="green" size="sm" inset="top bottom">
                                    in queue
                                </flux:badge>
                            @else
                                <flux:badge color="orange" size="sm" inset="top bottom">
                                    not in queue
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link href="/operator/vehicles/{{$vehicle->id}}" variant="subtle" wire:navigate>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                            </flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                    {{-- <flux:modal name="edit-profile-{{ $vehicle->id }}" class="w-full space-y-6" style="max-width: 672px;">
                        <div class="space-y-6">
                            <div>
                                <div>
                                    <flux:heading size="lg">Vehicle Information</flux:heading>
                                    <flux:subheading>Viewing details for 
                                        <strong>{{ $vehicle->vehicle_type }} </strong> 
                                            with the plate number of 
                                        <strong>{{ $vehicle->plate_number }}</strong>
                                    </flux:subheading>
                                </div>
                                   <div class="space-y-4 w-full border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-800/50">
                                    <div class="grid w-full grid-cols-2 gap-6 text-sm">
                                        <div>
                                            <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Vehicle Type</span>
                                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $vehicle->vehicle_type }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Date registered</span>
                                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $vehicle->created_at->format('M d, Y') }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Plate Number</span>
                                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $vehicle->plate_number }}</span>
                                        </div>
                                        <div>
                                            <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Route</span>
                                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $vehicle->route->terminal->municipality }}</span>
                                        </div>
                                    </div>
                                    <flux:input label="Name" placeholder="Your name" />
                                    <flux:input label="Date of birth" type="date" />
                                    <div class="flex">
                                        <flux:spacer />
                                        <flux:button type="submit" variant="primary">Save changes</flux:button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </flux:modal> --}}
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>
</div>