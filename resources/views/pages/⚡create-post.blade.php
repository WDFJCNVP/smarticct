<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;

use App\Models\Vehicle;
use App\Models\OperatorTicketRate;
use App\Models\Post;

new class extends Component
{
    use WithFileUploads;

    public array $attachments = [];
    public string $body = '';
    public ?string $type = 'announcement';
    public string $vehicle_type = "";
    public bool $is_post_preview = false;
    public ?string $from = null;
    public ?string $to = null;

    public function postPreview() {
        $this->is_post_preview = false;
        $this->is_post_preview = true;
    }

    public function isRenting() {
        $this->type = $this->type === 'announcement' ? 'rental' : 'announcement';
    }

    #[Computed]
    public function getVehicleTypes() {
        return OperatorTicketRate::get('vehicle_type');
    }

    protected function rules(): array
    {
        return [
            'body'          => 'required|string|max:255',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'image|max:2048',
            'vehicle_type'  => 'nullable|string|required_if:type,rental',
            'from'          => 'nullable|string',
            'to'            => 'nullable|string',
        ];
    }

    #[Computed]
    public function getVehicles()
    {
        if (auth()->user()->role !== 'operator') {
            return collect();
        }

        return Vehicle::where('user_id', auth()->id())->get('vehicle_type');
    }

    public function publish()
    {
        $validated_attributes  = $this->validate();

        $storedAttachments = [];

        foreach ($this->attachments as $attachment) {
            $storedAttachments[] = $attachment->store('posts', 'public');
        }

        if ($this->type === null) {
            $this->type = 'announcement';
        }

        Post::create([
            'user_id'  => auth()->id(),
            'type'     => $this->type,
            'body'     => $validated_attributes['body'],
            'status'   => 'published',
            'metadata' => [
                'from'         => $validated_attributes['from'],
                'to'           => $validated_attributes['to'],
                'vehicle_type' => $validated_attributes['vehicle_type'],
                'attachments'  => $storedAttachments,
            ],
        ]);

        $this->reset(['attachments', 'body', 'vehicle_type']);
        $this->resetValidation();
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }
};
?>
<div>
    <flux:card class="shrink-0">
        <div class="flex items-start gap-3">
            <div class="flex gap-3 flex-1">
                <div>
                    <flux:avatar size="sm" name="{{ auth()->user()->name }}" />
                </div>
                <div class="flex-1">
                    <x-input
                        wire:model="body"
                        placeholder="What's on your mind?"
                        class="flex-1 rounded-full"
                    />

                    <div wire:loading wire:target="attachments" class="text-xs text-zinc-400 mt-2 ml-11">
                        Uploading...
                    </div>

                    @if (!empty($attachments))
                        <div class="grid grid-cols-4 gap-2 mt-3">
                            @foreach ($attachments as $index => $attachment)
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
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="cursor-pointer flex items-center justify-center">
                    <flux:icon.photo class="w-6 h-6"/>
                    <input
                        type="file"
                        wire:model="attachments"
                        multiple
                        accept="image/*"
                        class="hidden"
                    >
                </label>

                <flux:button
                    variant="primary"
                    type="button"
                    wire:click="postPreview"
                    wire:loading.attr="disabled"
                    wire:target="publish"
                    size="sm"
                >
                    Post
                </flux:button>
            </div>

        </div>
    </flux:card>

    <flux:modal wire:model="is_post_preview" class="md:w-196">
        @if ($this->body)
            <div class="space-y-6">
                <flux:textarea wire:model="body" label="Post description" placeholder="Description" />

                @if (!empty($attachments))
                    <div class="grid grid-cols-4 gap-2 mt-3">
                        @foreach ($attachments as $index => $attachment)
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

                <div>
                    <flux:label class="mb-2">Post type</flux:label>

                    <flux:callout 
                        variant="secondary"
                        icon="information-circle" 
                        heading="If the 'Renting' switch is turned off, the post type will default to an announcement. Otherwise, it will be treated as a renting type." 
                        class="mb-2"
                    />

                    <flux:switch wire:click="isRenting" label="Renting" align="left" />
                </div>

                <div>
                    @if ($type === 'rental' && auth()->user()->role === 'operator')
                        <div class="flex-none">
                            @if ($this->getVehicles->isNotEmpty())

                                <flux:label class="mt-4 mb-2" >Vehicle type</flux:label>

                                <flux:select wire:model="vehicle_type" placeholder="Select a vehicle from your fleet">
                                    @foreach ($this->getVehicles as $vehicle)

                                        <flux:select.option value="{{ $vehicle->vehicle_type }}">
                                            {{ $vehicle->vehicle_type }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <x-text size="sm" class="text-zinc-500">Add a vehicle to your fleet to offer it for rent.</x-text>
                            @endif
                        </div>
                        <flux:label class="mt-4 mb-2" >Destination range (optional)</flux:label>

                        <x-inputs-container>
                            
                            <x-input wire:model="from" placeholder="From" />
                            <x-input wire:model="to" placeholder="To" />

                        </x-inputs-container>

                    @endif

                    @if ($type === 'rental' && auth()->user()->role === 'commuter')

                        <div class="flex-none">
                            @if ($this->getVehicleTypes->isNotEmpty())

                                <flux:label class="mt-4 mb-2" >Vehicle type</flux:label>

                                <flux:select wire:model="vehicle_type" placeholder="Select a vehicle from your fleet">
                                    @foreach ($this->getVehicleTypes as $vehicle)

                                        <flux:select.option value="{{ $vehicle->vehicle_type }}">
                                            {{ $vehicle->vehicle_type }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <x-text size="sm" class="text-zinc-500">No record found. Please contact the admin.</x-text>
                            @endif
                        </div>

                        <flux:label class="mt-4 mb-2" >Destination range (optional)</flux:label>

                        <x-inputs-container>
                            <x-input wire:model="from" placeholder="From" />
                            <x-input wire:model="to" placeholder="To" />
                        </x-inputs-container>
                    @endif
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:button wire:click="publish" variant="primary">Post</flux:button>
                </div>
            </div>
        @else
        <x-text>Please fill-up the post body</x-text>
        @endif

    </flux:modal>
</div>