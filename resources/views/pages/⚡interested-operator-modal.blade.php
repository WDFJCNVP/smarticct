<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Flux\Flux;

use App\Models\Vehicle;
use App\Models\PostInterest;
use App\Services\PostInterestService;

new class extends Component
{
    use WithFileUploads;

    public $selected_post;
    public $message;
    public $vehicle_images = [];
    public $selected_vehicle_type = "";
    public ?int $total_seats_capacity = null;
    public ?string $vehicle_name = null;
    public ?string $destination_coverage = null;
    public ?string $available_from = null;
    public ?string $available_until = null;
    public $vehicle_id;

    public function updatedSelectedVehicleType($vehicleId)
    {
        $vehicle = Vehicle::where('user_id', auth()->id())->find($vehicleId);
        $this->vehicle_id = $vehicle->id ?? null;
        $this->total_seats_capacity = $vehicle->total_seats ?? null;
    }

    #[Computed]
    public function getOperatorVehicles()
    {
        // A commuter's rental request post specifies the vehicle type they
        // need (stored in metadata.vehicle_type). Operators should only be
        // able to offer a vehicle of that same type, not their whole fleet.
        return Vehicle::where('user_id', auth()->id())
            ->when(
                $this->selected_post?->metadata['vehicle_type'] ?? null,
                fn ($query, $vehicleType) => $query->where('vehicle_type', $vehicleType)
            )
            ->get(['id', 'vehicle_type', 'total_seats']);
    }

    public function removeAttachment($index)
    {
        unset($this->vehicle_images[$index]);
        $this->vehicle_images = array_values($this->vehicle_images);
    }

    public function rules()
    {
        return [
            'selected_vehicle_type' => 'required',
            'vehicle_name'          => 'required|string|max:255',
            'total_seats_capacity'  => 'required|integer|min:1',
            'destination_coverage'  => 'required|string|max:255',
            'available_from'        => 'required|date',
            'available_until'       => 'required|date',
            'message'               => 'required|string|max:255',
            'vehicle_id'            => 'required|integer|exists:vehicles,id',
            'vehicle_images'        => 'nullable|array|max:5',
            'vehicle_images.*'      => 'required|image|max:5120|mimes:jpg,jpeg,png,webp',
        ];
    }

    public function sendRequest(PostInterestService $service)
    {
        $validated = $this->validate();
        $service->create($this->selected_post, $validated);

        if($service) {
            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Your request has been submitted.',
                text: 'You can see the updates in your notifications.',
            );
        }
        $this->dispatch('interest-deleted');
    }
};
?>

<div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                Send Interest
            </flux:heading>
            <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                Review your rental offer details before sending.
            </flux:text>
        </div>

        <flux:modal.close>
            <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                <flux:icon name="x-mark" class="w-5 h-5" />
            </button>
        </flux:modal.close>
    </div>

    <form wire:submit="sendRequest" enctype="multipart/form-data" class="space-y-5">
        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Vehicle type you want to offer
            </flux:label>

            @if ($this->getOperatorVehicles->isNotEmpty())
                <flux:select
                    wire:model.live="selected_vehicle_type"
                    placeholder="Select vehicle type"
                    required
                    class="mt-1"
                >
                    @foreach ($this->getOperatorVehicles as $vehicle)
                        <flux:select.option value="{{ $vehicle->id }}">
                            {{ $vehicle->vehicle_type }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <x-text variant="subtle" style="font-size: var(--text-table-row)" class="mt-1">
                    None of your registered vehicles match the vehicle type ({{ $this->selected_post->metadata['vehicle_type'] ?? 'requested' }}) this commuter is asking for.
                </x-text>
            @endif
        </flux:field>

        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Vehicle name/model
            </flux:label>
            <x-input
                wire:model="vehicle_name"
                placeholder="e.g. Mitsubishi L300"
                required
                class="mt-1"
            />
        </flux:field>

        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Seating Capacity
            </flux:label>
            <x-input
                wire:model="total_seats_capacity"
                type="number"
                disabled
                class="mt-1 bg-light-subtle dark:bg-dark-subtle"
            />
        </flux:field>

        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Destination coverage
            </flux:label>
            <x-input
                wire:model="destination_coverage"
                placeholder="e.g. Entire Camarines Sur"
                required
                class="mt-1"
            />
        </flux:field>

        <div>
            <flux:heading size="sm" class="font-secondary font-medium text-light-txt-body dark:text-dark-txt-primary mb-2">
                Available Dates
            </flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Available from
                    </flux:label>
                    <x-input wire:model="available_from" type="date" required class="mt-1" />
                </flux:field>
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Available until
                    </flux:label>
                    <x-input wire:model="available_until" type="date" required class="mt-1" />
                </flux:field>
            </div>
        </div>

        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Upload your vehicle images
            </flux:label>
            <div class="flex flex-wrap items-center gap-3 mt-1">
                <label
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                >
                    <flux:icon.photo class="w-4 h-4" />
                    Choose photos
                    <input
                        type="file"
                        wire:model="vehicle_images"
                        multiple
                        accept="image/*"
                        class="hidden"
                    />
                </label>
                <div wire:loading wire:target="vehicle_images" class="text-timestamp text-light-txt-muted">
                    Uploading...
                </div>
            </div>

            @if (!empty($vehicle_images))
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mt-3">
                    @foreach ($vehicle_images as $index => $attachment)
                        @if (is_object($attachment) && str_starts_with($attachment->getMimeType(), 'image/'))
                            <div class="relative group aspect-square rounded-lg overflow-hidden border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle">
                                <img src="{{ $attachment->temporaryUrl() }}" class="object-cover w-full h-full" alt="Preview">
                                <button
                                    type="button"
                                    wire:click="removeAttachment({{ $index }})"
                                    class="absolute top-1 right-1 flex items-center justify-center size-6 rounded-full bg-zinc-900/80 hover:bg-zinc-900 text-white cursor-pointer"
                                    title="Remove image"
                                >
                                    <flux:icon name="x-mark" class="size-3.5" color="white" />
                                </button>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </flux:field>

        <flux:field>
            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                Message to client
            </flux:label>
            <flux:textarea
                wire:model="message"
                placeholder="Enter your message to the client"
                required
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