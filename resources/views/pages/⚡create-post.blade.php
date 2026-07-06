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
    public ?string $type = null;

    public string $vehicle_type = "";

    #[Computed]
    public function getVehicleTypes() {
        return OperatorTicketRate::get('vehicle_type');
    }

    public function mount(): void
    {

        // dd($this->getVehicleTypes);

        if (in_array(auth()->user()->role, ['operator', 'commuter'])) {
            $this->type = 'rental';
        }
    }

    protected function rules(): array
    {
        return [
            'body' => 'required|string|max:255',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'image|max:2048',
            'vehicle_type' => 'nullable|string|required_if:type,rental',
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
        $this->validate();

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
            'body'     => $this->body,
            'status'   => 'published',
            'metadata' => [
                'vehicle_type' => $this->type === 'rental' ? $this->vehicle_type : null,
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

<flux:card class="shrink-0">
    <form wire:submit="publish">
        <div class="flex items-start gap-3">
            <div class="flex gap-3 flex-1">
                <div>
                    <flux:avatar size="sm" name="{{ auth()->user()->name }}" />
                </div>
                <div class="flex-1">
                    @if (auth()->user()->role === 'admin' || auth()->user()->role === 'cashier')
                        <x-text size="sm" variant="strong" class="block mb-1">Post an announcement</x-text>
                        <x-text size="sm" class="text-zinc-500 block mb-2">Your role can only post announcements.</x-text>
                    @endif

                    <flux:input
                        wire:model.live.debounce.300ms="body"
                        placeholder="What's on your mind?"
                        class="flex-1 rounded-full"
                    />
                    @error('body')
                        <x-text size="sm" class="text-red-500 block mt-2 ml-11">{{ $message }}</x-text>
                    @enderror

                    <div wire:loading wire:target="attachments" class="text-xs text-zinc-400 mt-2 ml-11">
                        Uploading...
                    </div>

                    @error('attachments')
                        <x-text size="sm" class="text-red-500 block mt-2 ml-11">{{ $message }}</x-text>
                    @enderror
                    @error('attachments.*')
                        <x-text size="sm" class="text-red-500 block mt-2 ml-11">{{ $message }}</x-text>
                    @enderror

                    @if (!empty($attachments))
                        <div class="grid grid-cols-4 gap-2 mt-3 ml-11">
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

                    <div class="flex gap-3 items-center mt-3">

                        @if (auth()->user()->role === 'operator' || auth()->user()->role === 'commuter')
                            <flux:radio.group wire:model.live="type" variant="segmented" size="sm">
                                <flux:radio value="rental" label="{{ auth()->user()->role === 'operator' ? 'Offer a vehicle' : 'Request a vehicle' }}" />
                                <flux:radio value="announcement" label="Announcement" />
                            </flux:radio.group>
                        @endif

                        @if ($type === 'rental' && auth()->user()->role === 'operator')
                            <div class="w-fit flex-none">
                                @if ($this->getVehicles->isNotEmpty())
                                    <flux:select wire:model="vehicle_type" placeholder="Select a vehicle from your fleet" size="sm">
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
                        @endif

                        @if ($type === 'rental' && auth()->user()->role === 'commuter')
                            <div class="w-fit flex-none">
                                <flux:select wire:model="vehicle_type" placeholder="Vehicle type you need" size="sm">
                                    @foreach ($this->getVehicleTypes as $option)
                                        <flux:select.option value="{{ $option->vehicle_type}}">{{ $option->vehicle_type }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif

                        @error('vehicle_type')
                            <x-text size="sm" class="text-red-500">{{ $message }}</x-text>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="cursor-pointer flex items-center justify-center">
                    <flux:icon.photo class="size-5 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors" />
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
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="publish"
                    size="sm"
                >
                    Post
                </flux:button>
            </div>

        </div>
    </form>
</flux:card>