<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\User;
use App\Models\Vehicle;

new class extends Component
{
    public User $user;

    public $name;
    public $email;
    public $address;
    public bool $confirmingDelete = false;

    public $vehicle_type;
    public $total_seats;
    public $plate_number;
    public $created_at;

    public function mount($user_id) {
        $this->user    = User::findOrFail($user_id);
        $this->name    = $this->user->name;
        $this->email   = $this->user->email;
        $this->address = $this->user->address;
    }

    #[Computed]
    public function getVehicle() {
       return Vehicle::where('user_id', $this->user->id)->get();
    }

    public function save() {
        $validated = $this->validate([
            'name'    => ['required'],
            'email'   => ['required', 'email'],
            'address' => ['required'],
        ]);

        $this->user->update($validated);

        $this->dispatch('user-saved');
    }

    public function deleteUser() {
        $this->user->delete();
        $this->redirect('/admin/users');
    }
};
?>

<div>
    <div>
        <flux:heading size="lg">User Information</flux:heading>
        <flux:subheading>Viewing details for {{ $user->name }}</flux:subheading>
    </div>

    <div class="space-y-4 w-full border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-800/50">
        <div class="flex items-center gap-4">
            <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" />
            <div>
                <div class="font-semibold text-base text-zinc-800 dark:text-zinc-200">{{ $user->name }}</div>
                <div class="text-sm text-zinc-500">{{ $user->email }}</div>
            </div>
        </div>

            <flux:separator variant="dashed" />

        <div class="grid w-full grid-cols-2 gap-6 text-sm">
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Role</span>

                @if ($user->role === 'operator')
                    <flux:badge color="blue" size="sm" inset="top bottom">Operator</flux:badge>
                @else
                    <flux:badge color="yellow" size="sm" inset="top bottom">Commuter</flux:badge>
                @endif
            </div>
            <div>
                <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Joined</span>
                <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $user->created_at->format('M d, Y') }}</span>
            </div>
        </div>

        <form wire:submit="save">
            <div class="grid w-full grid-cols-2 gap-6 mb-4">
                <flux:input label="Name" wire:model="name" class="w-full" />
                <flux:input label="Email" wire:model="email" class="w-full" />
                <flux:input label="Address" wire:model="address" class="w-full" />
            </div>

            <flux:separator variant="dashed" />

            @if ($user->role === 'operator')

                <flux:heading size="lg" class="mb-6 mt-3">Vehicle Information</flux:heading>

                @foreach ($this->getVehicle as $index => $vehicle)
                    <div class="flex items-center">
                        <div class="flex-1 flex">
                            <flux:badge color="green" size="sm" class="mb-4" inset="top bottom">Vehicle {{ $index + 1 }}</flux:badge>
                        </div>
                        <flux:link href="#">
                            <flux:icon.trash class="w-5.5 h-5.5" />
                        </flux:link>
                    </div>

                    <div class="grid w-full grid-cols-2 gap-6 mb-12">
                        <flux:input label="Vehicle Type" value="{{ $vehicle->vehicle_type }}" class="w-full" />
                        <flux:input label="Plate No." value="{{ $vehicle->plate_number }}" class="w-full" />
                        <flux:input label="Total Seats" value="{{ $vehicle->total_seats }}" class="w-full" />
                        <flux:input label="Date Registered" value="{{ $vehicle->created_at }}" class="w-full" />
                    </div>
                @endforeach
            @endif

            <div class="flex w-full gap-2 mt-6">
                <div class="flex flex-1">
                    <flux:button
                        type="button"
                        variant="danger"
                        wire:click="$set('confirmingDelete', true)"
                    >
                        Delete
                    </flux:button>
                </div>
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
            </div>
        </form>

        @if ($confirmingDelete)
            <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-800 p-4 space-y-3">
                <div>
                    <p class="font-semibold text-red-700 dark:text-red-400">Are you sure?</p>
                    <p class="text-sm text-red-600 dark:text-red-300 mt-1">
                        You're about to delete <strong>{{ $user->name }}</strong>.
                        This action cannot be reversed.
                    </p>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button
                        size="sm"
                        wire:click="$set('confirmingDelete', false)"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="danger"
                        wire:click="deleteUser"
                    >
                        Yes, delete user
                    </flux:button>
                </div>
            </div>
        @endif

    </div>
</div>