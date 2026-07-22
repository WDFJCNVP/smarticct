<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;

use App\Services\PostService;

new class extends Component
{
   use WithFileUploads;

   public $selected_post;

   #[Validate('required|string|max:100')]
   public ?string $name = null;

   #[Validate('required|string|max:11')]
   public ?string $phone_number = null;

   #[Validate('required|string|max:255')]
   public ?string $address = null;

   #[Validate('nullable|string|max:255')]
   public ?string $email_address = null;

   public $trip_type = 'round_trip';

   #[Validate('required|string|max:255')]
    public $pick_up_location;

    #[Validate('required|string|max:255')]
    public $drop_off_location;

    #[Validate('required|string|max:255')]
    public ?string $trip_date = null;

    #[Validate('required|numeric|max:25')]
    public ?int $body_count = null;

   #[Validate('required|image|max:2048')]
   public $user_valid_id;
   
    #[Validate('required_if:has_driver,true|nullable|image|max:2048')]
    public $driver_valid_id;

    #[Validate('required_if:has_driver,true|nullable|string|max:255')]
    public ?string $driver_name = null;

    #[Validate('required_if:has_driver,true|nullable|numeric|min:18')]
    public ?int $driver_age = null;

    #[Validate('required_if:has_driver,true|nullable|string|max:255')]
    public ?string $driver_home_address = null;

    #[Validate('required_if:has_driver,true|nullable|string|max:20')]
    public ?string $driver_contact_number = null;

    #[Validate('required|string|max:255')]
    public ?string $purpose = null;

    public bool $has_driver = false;
    
    public function switchTripType($trip_type) {
        $this->trip_type = null;

        $this->trip_type = $trip_type;
    }

    public function removeUserAttachment() {
        $this->user_valid_id = null;
    }
    
    public function removeDriverAttachment() {
        $this->driver_valid_id = null;
    }

   public function saveInterest() {

    $attributes = $this->validate();

    $user_valid_id_path = $this->user_valid_id ? $this->user_valid_id->store('valid_id', 'public') : null;
    $driver_valid_id_path = $this->driver_valid_id ? $this->driver_valid_id->store('valid_id', 'public') : null;

    app(PostService::class)->saveTripRequest(
        [
            'post_id'           => $this->selected_post->id,
            'user_id'           => auth()->id(),
            'message'           => $attributes['purpose'],
            'body_count'        => $attributes['body_count'],
            'pick_up_location'  => $attributes['pick_up_location'],
            'drop_off_location' => $attributes['drop_off_location'],
            'trip_date'         => $attributes['trip_date'],
            'trip_type'         => $this->trip_type,
            'status'            => 'pending',
            'metadata'          => [
                'valid_ids' => [
                    'user_valid_id'     => $user_valid_id_path,
                    'driver_valid_id'   => $driver_valid_id_path,
                ],
                'driver_name'           => $attributes['driver_name'],
                'driver_age'            => $attributes['driver_age'],
                'driver_home_address'   => $attributes['driver_home_address'],
                'driver_contact_number' => $attributes['driver_contact_number'],
            ]
        ]);

   }
};
?>

<div>
    <form wire:submit="saveInterest">

        @csrf

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Update profile</flux:heading>
                <flux:text class="mt-2">Make changes to your personal details.</flux:text>
            </div>

            <flux:separator variant="subtle" class="mb-4"/>

            <x-heading>Personal Information</x-heading>

            <flux:callout 
                variant="secondary" 
                icon="information-circle" 
                heading="We've pre-filled this using your saved settings. Notice a mistake? You can update your information anytime inside your Account Settings"
                />

            <x-inputs-container class="mb-4">
                <flux:input label="Name"         placeholder="Your name" wire:model="name" disabled/>
                <flux:input label="Phone number" placeholder="Your phone number" wire:model="phone_number" disabled/>
                <flux:input label="Home address" placeholder="Your address" wire:model="address" disabled/>
                <flux:input label="Email address" placeholder="Email addres" wire:model="email_address" disabled/>
            </x-inputs-container>

            <flux:separator variant="subtle" class="mb-4"/>

            <x-heading>Trip Information</x-heading>


            {{-- <flux:select label="Pick-up Location" wire:model="pick_up_location" >
                <flux:select.option value="" selected>Select pick-up location</flux:select.option>
                <flux:select.option >Naga</flux:select.option>
                <flux:select.option >Nabua</flux:select.option>
            </flux:select> --}}

            <div x-data="{ tab: 'round_trip' }" class="w-full">
                <div class="flex gap-1 p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 ">
                    <button
                        type="button"
                        @click="tab = 'round_trip'"
                        wire:click="switchTripType('round_trip')"
                        :class="tab === 'round_trip' ? 'bg-white dark:bg-zinc-700 shadow-sm' : 'text-zinc-500'"
                        class="flex-1 text-sm font-medium py-1.5 rounded-md cursor-pointer transition-colors w-fit"
                    >
                        Round Trip
                    </button>
                    <button
                        type="button"
                        @click="tab = 'one_way'"
                        wire:click="switchTripType('one_way')"
                        :class="tab === 'one_way' ? 'bg-white dark:bg-zinc-700 shadow-sm' : 'text-zinc-500'"
                        class="flex-1 text-sm font-medium py-1.5 rounded-md cursor-pointer transition-colors w-fit"
                    >
                        One Way
                    </button>
                </div>

                <div x-show="tab === 'round_trip'" class="mt-3 flex items-center gap-2 w-full">
                    <div class="w-full">
                        <x-input placeholder="e.g. Nabua" label="From" wire:model="pick_up_location"/>
                    </div>

                    <flux:icon.arrows-right-left class="shrink-0 mt-8" size="sm"/>

                    <div class="w-full">
                        <x-input placeholder="e.g. Legaspi" label="To" wire:model="drop_off_location"/>
                    </div>
                </div>

                <div x-show="tab === 'one_way'" class="mt-3 flex items-center gap-2 w-full">
                    <div class="w-full">
                        <x-input placeholder="e.g. Nabua" label="From" wire:model="pick_up_location"/>
                    </div>

                    <flux:icon.arrow-right class="shrink-0 mt-8" size="sm"/>

                    <div class="w-full">
                        <x-input placeholder="e.g. Legaspi" label="To" wire:model="drop_off_location"/>
                    </div>
                </div>
            </div>

            <x-inputs-container>

                <x-input label="Trip date" placeholder="Date" type="date" wire:model="trip_date"/>

                <x-input label="Number of Passenger" placeholder="e.g. 20" type="number" wire:model="body_count"/>

            </x-inputs-container>

            <flux:separator variant="subtle" class="mb-4"/>

            <div>
                @if (!$this->user_valid_id)
                    <flux:input
                        label="Valid ID"
                        name="valid_id"
                        type="file"
                        wire:model="user_valid_id"
                        accept="image/*"
                        icon="photo"
                        placeholder="Valid ID"
                        required
                        />

                    <div wire:loading wire:target="user_valid_id" class="text-xs text-zinc-400 mt-2 ml-11">
                        Uploading...
                    </div>
                @else
                    <flux:label class="mb-4">Attached valid id</flux:label>
                    <div class="relative group aspect-square rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
                        <img src="{{ $user_valid_id->temporaryUrl() }}" class="object-cover w-full h-full" alt="Preview">
                        <button
                            type="button"
                            wire:click="removeUserAttachment"
                            class="absolute top-1 right-1 flex items-center justify-center size-6 rounded-full bg-zinc-900/80 hover:bg-zinc-900 text-white cursor-pointer"
                            title="Remove image"
                        >
                            <flux:icon name="x-mark" class="size-3.5" color="white" />
                        </button>
                    </div>
                @endif

            </div>

            <flux:separator variant="subtle" class="mb-4"/>

            <div>
                <flux:field variant="inline" class="w-fit mb-4">
                    <flux:label>Driver included</flux:label>

                    <flux:switch name="has_driver" wire:model.live="has_driver" />

                    <flux:error name="has_driver" />
                </flux:field>

                @if ($this->has_driver)

                <x-inputs-container>
                    <x-input label="Driver full name" placeholder="e.g. Juan Tamad" wire:model="driver_name"/>
                    <x-input label="Driver age" placeholder="e.g. 34" wire:model="driver_age"/>
                    <x-input label="Driver home address" placeholder="e.g. 123 Main St" wire:model="driver_home_address"/>
                    <x-input label="Driver contact number" placeholder="e.g. 09123456789" wire:model="driver_contact_number"/>
                </x-inputs-container>

                    @if (! $this->driver_valid_id)

                        <flux:separator variant="subtle" class="mb-4"/>

                        <flux:input
                            label="Driver valid ID"
                            name="valid_id"
                            type="file"
                            wire:model="driver_valid_id"
                            accept="image/*"
                            />

                        <div wire:loading wire:target="driver_valid_id" class="text-xs text-zinc-400 mt-2 ml-11">
                            Uploading...
                        </div>
                    @else

                        <flux:separator variant="subtle" />

                        <flux:label class="mb-4">Attached valid id</flux:label>
                        <div class="relative group aspect-square rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
                            <img src="{{ $driver_valid_id->temporaryUrl() }}" class="object-cover w-full h-full" alt="Preview">
                            <button
                                type="button"
                                wire:click="removeDriverAttachment"
                                class="absolute top-1 right-1 flex items-center justify-center size-6 rounded-full bg-zinc-900/80 hover:bg-zinc-900 text-white cursor-pointer"
                                title="Remove image"
                            >
                                <flux:icon name="x-mark" class="size-3.5" color="white" />
                            </button>
                        </div>
                    @endif

                @endif
            </div>

            <flux:textarea label="Purpose" placeholder="Enter your purpose" wire:model="purpose"/>

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </form>
</div>