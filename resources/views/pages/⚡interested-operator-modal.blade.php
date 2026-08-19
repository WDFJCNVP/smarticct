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
        $vehicle = Vehicle::where('user_id', auth()->id())
            ->find($vehicleId);

        $this->vehicle_id = $vehicle->id;

        if ($vehicle) {
            $this->total_seats_capacity = $vehicle->total_seats;
        } else {
            $this->total_seats_capacity = null;
        }
    }

    #[Computed]
    public function getOperatorVehicles()
    {
        return Vehicle::where('user_id', auth()->id())
            ->get(['id', 'vehicle_type', 'total_seats']);
    }

    public function removeAttachment($index)
    {
        unset($this->vehicle_images[$index]);
        $this->vehicle_images = array_values($this->vehicle_images);
    }

    public function rules() {
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

<div>
    <div class="flex items-center gap-1 mb-4">
        <flux:avatar size="sm" name="{{ $this->selected_post->user->name }}" />
        <div>
            <x-text size="lg" variant="strong">{{ $selected_post->user->name }}</x-text>
        </div>
    </div>

    <form wire:submit="sendRequest" enctype="multipart/form-data">
        <x-inputs-container>
            <flux:select
                wire:model.live="selected_vehicle_type"
                label="Vehicle type you want to offer"
                placeholder="Select vehicle type"
                required
            >
                @foreach ($this->getOperatorVehicles as $vehicle)
                    <flux:select.option value="{{ $vehicle->id }}">
                        {{ $vehicle->vehicle_type }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <x-input wire:model="vehicle_name" label="Vehicle name/model" placeholder="e.g. Mitsubishi L300" required/>

            <x-input
                wire:model="total_seats_capacity"
                label="Seating Capacity"
                placeholder="e.g. 18"
                type="number"
                disabled
            />

            <x-input wire:model="destination_coverage" label="Destination coverage" placeholder="e.g. Entire Camarines Sur" required/>
        </x-inputs-container>

        <x-text variant="strong" size="lg" class="my-4">Available Dates</x-text>

        <x-inputs-container>
            <x-input wire:model="available_from" type="date" label="Available from" required/>
            <x-input wire:model="available_until" type="date" label="Available until" required/>
        </x-inputs-container>

        <div class="mt-4">
            @if (!$this->vehicle_images)
                <flux:input
                    label="Upload your vehicle images"
                    type="file"
                    wire:model="vehicle_images"
                    accept="image/*"
                    icon="photo"
                    multiple
                    required
                    />

                <div wire:loading wire:target="vehicle_images" class="text-xs text-zinc-400 mt-2 ml-11">
                    Uploading...
                </div>
            @else
                <flux:label>Attached images</flux:label>
                    @if (!empty($vehicle_images))
                        <div class="grid grid-cols-4 gap-2 mt-3">
                            @foreach ($vehicle_images as $index => $attachment)
                                @if (is_object($attachment) && str_starts_with($attachment->getMimeType(), 'image/'))
                                    <div class="relative group aspect-square rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
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
            @endif

        </div>

        <div class="my-4">
        <flux:textarea label="Message to client" placeholder="Enter your message to the client" wire:model="message" required/>
        </div>

        <div class="mt-2">
            <x-button type="submit" variant="primary">Send</x-button>
        </div>
    </form>
</div>