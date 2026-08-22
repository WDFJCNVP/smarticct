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
     $this->dispatch('interest-deleted');
   }
};
?>

<div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                Send Trip Request
            </flux:heading>
            <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Review your trip details before sending.
            </flux:text>
        </div>

        <flux:modal.close>
            <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                <flux:icon name="x-mark" class="w-5 h-5" />
            </button>
        </flux:modal.close>
    </div>

    <form wire:submit="saveInterest" class="space-y-5">

        @csrf

        <div>
            <flux:heading size="sm" class="font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary mb-2">
                Personal Information
            </flux:heading>

            <flux:callout
                variant="secondary"
                icon="information-circle"
                heading="We've pre-filled this using your saved settings. Notice a mistake? You can update your information anytime inside your Account Settings"
                class="mb-3"
                />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Name
                    </flux:label>
                    <x-input placeholder="Your name" wire:model="name" disabled class="mt-1 bg-light-subtle dark:bg-dark-subtle" />
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Phone number
                    </flux:label>
                    <x-input placeholder="Your phone number" wire:model="phone_number" disabled class="mt-1 bg-light-subtle dark:bg-dark-subtle" />
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Home address
                    </flux:label>
                    <x-input placeholder="Your address" wire:model="address" disabled class="mt-1 bg-light-subtle dark:bg-dark-subtle" />
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Email address
                    </flux:label>
                    <x-input placeholder="Email address" wire:model="email_address" disabled class="mt-1 bg-light-subtle dark:bg-dark-subtle" />
                </flux:field>
            </div>
        </div>

        <div class="border-t border-light-bd-default dark:border-dark-bd-default pt-5">
            <flux:heading size="sm" class="font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary mb-2">
                Trip Information
            </flux:heading>

            <div x-data="{ tab: 'round_trip' }" class="w-full">
                <div class="flex gap-1 p-0.5 rounded-lg bg-light-subtle dark:bg-dark-subtle">
                    <button
                        type="button"
                        @click="tab = 'round_trip'"
                        wire:click="switchTripType('round_trip')"
                        :class="tab === 'round_trip' ? 'bg-white dark:bg-zinc-700 shadow-sm' : 'text-light-txt-muted dark:text-dark-txt-muted'"
                        class="flex-1 text-sm font-secondary font-medium py-1.5 rounded-md cursor-pointer transition-colors w-fit"
                    >
                        Round Trip
                    </button>
                    <button
                        type="button"
                        @click="tab = 'one_way'"
                        wire:click="switchTripType('one_way')"
                        :class="tab === 'one_way' ? 'bg-white dark:bg-zinc-700 shadow-sm' : 'text-light-txt-muted dark:text-dark-txt-muted'"
                        class="flex-1 text-sm font-secondary font-medium py-1.5 rounded-md cursor-pointer transition-colors w-fit"
                    >
                        One Way
                    </button>
                </div>

                <div x-show="tab === 'round_trip'" class="mt-3 flex items-center gap-2 w-full">
                    <flux:field class="w-full">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            From
                        </flux:label>
                        <x-input placeholder="e.g. Nabua" wire:model="pick_up_location" class="mt-1" />
                    </flux:field>

                    <flux:icon.arrows-right-left class="shrink-0 mt-8" size="sm"/>

                    <flux:field class="w-full">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            To
                        </flux:label>
                        <x-input placeholder="e.g. Legaspi" wire:model="drop_off_location" class="mt-1" />
                    </flux:field>
                </div>

                <div x-show="tab === 'one_way'" class="mt-3 flex items-center gap-2 w-full">
                    <flux:field class="w-full">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            From
                        </flux:label>
                        <x-input placeholder="e.g. Nabua" wire:model="pick_up_location" class="mt-1" />
                    </flux:field>

                    <flux:icon.arrow-right class="shrink-0 mt-8" size="sm"/>

                    <flux:field class="w-full">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            To
                        </flux:label>
                        <x-input placeholder="e.g. Legaspi" wire:model="drop_off_location" class="mt-1" />
                    </flux:field>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Trip date
                    </flux:label>
                    <x-input placeholder="Date" type="date" wire:model="trip_date" class="mt-1" />
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Number of passengers
                    </flux:label>
                    <x-input placeholder="e.g. 20" type="number" wire:model="body_count" class="mt-1" />
                </flux:field>
            </div>
        </div>

        <div class="border-t border-light-bd-default dark:border-dark-bd-default pt-5">
            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Valid ID
                </flux:label>

                @if (!$this->user_valid_id)
                    <div class="flex flex-wrap items-center gap-3 mt-1">
                        <label
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                        >
                            <flux:icon.photo class="w-4 h-4" />
                            Choose photo
                            <input
                                type="file"
                                wire:model="user_valid_id"
                                accept="image/*"
                                required
                                class="hidden"
                            />
                        </label>
                        <div wire:loading wire:target="user_valid_id" class="text-timestamp text-light-txt-muted">
                            Uploading...
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mt-3">
                        <div class="relative group aspect-square rounded-lg overflow-hidden border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle">
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
                    </div>
                @endif
            </flux:field>
        </div>

        <div class="border-t border-light-bd-default dark:border-dark-bd-default pt-5">
            <flux:field variant="inline" class="w-fit mb-4">
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    Driver included
                </flux:label>

                <flux:switch name="has_driver" wire:model.live="has_driver" />

                <flux:error name="has_driver" />
            </flux:field>

            @if ($this->has_driver)

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Driver full name
                        </flux:label>
                        <x-input placeholder="e.g. Juan Tamad" wire:model="driver_name" class="mt-1" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Driver age
                        </flux:label>
                        <x-input placeholder="e.g. 34" wire:model="driver_age" class="mt-1" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Driver home address
                        </flux:label>
                        <x-input placeholder="e.g. 123 Main St" wire:model="driver_home_address" class="mt-1" />
                    </flux:field>
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Driver contact number
                        </flux:label>
                        <x-input placeholder="e.g. 09123456789" wire:model="driver_contact_number" class="mt-1" />
                    </flux:field>
                </div>

                @if (! $this->driver_valid_id)
                    <flux:field class="mt-4">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Driver valid ID
                        </flux:label>
                        <div class="flex flex-wrap items-center gap-3 mt-1">
                            <label
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                            >
                                <flux:icon.photo class="w-4 h-4" />
                                Choose photo
                                <input
                                    type="file"
                                    wire:model="driver_valid_id"
                                    accept="image/*"
                                    class="hidden"
                                />
                            </label>
                            <div wire:loading wire:target="driver_valid_id" class="text-timestamp text-light-txt-muted">
                                Uploading...
                            </div>
                        </div>
                    </flux:field>
                @else
                    <flux:field class="mt-4">
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Attached valid id
                        </flux:label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mt-3">
                            <div class="relative group aspect-square rounded-lg overflow-hidden border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle">
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
                        </div>
                    </flux:field>
                @endif
            @endif
        </div>

        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Purpose
            </flux:label>
            <flux:textarea
                wire:model="purpose"
                placeholder="Enter your purpose"
                rows="3"
                class="mt-1"
            />
        </flux:field>

        <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
            <flux:modal.close>
                <x-button
                    type="button"
                    variant="ghost"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Cancel
                </x-button>
            </flux:modal.close>
            <x-button
                type="submit"
                variant="primary"
                class="w-full sm:w-auto justify-center !bg-[color:var(--color-primary)] hover:!bg-[color:var(--color-primary-hover)] !text-white !font-secondary"
            >
                Send
            </x-button>
        </div>
    </form>
</div>