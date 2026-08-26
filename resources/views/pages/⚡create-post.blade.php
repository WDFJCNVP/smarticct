<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Illuminate\Validation\Rule;

use App\Services\PostService;
use App\Models\Vehicle;
use App\Models\OperatorTicketRate;
use App\Models\Post;

new class extends Component
{
    use WithFileUploads;

    public array $attachments = [];
    public string $body = '';
    public ?string $type = 'announcement';
    public bool $is_renting = false;
    public string $vehicle_type = "";
    public ?int $vehicle_id = null;
    public ?int $seats_offered = null;
    public bool $is_post_preview = false;
    public ?string $from = null;
    public ?string $to = null;

    public function mount()
    {
        $this->applyRoleDefaults();
    }

    /**
     * Announcements are admin/cashier only. Operators and commuters
     * always post rentals, so this needs to be re-applied any time
     * the form state is reset (e.g. after a successful publish),
     * not just once on mount().
     */
    private function applyRoleDefaults(): void
    {
        if (!in_array(auth()->user()->role, ['admin', 'cashier'])) {
            $this->type = 'rental';
            $this->is_renting = true;
        }
    }

    public function updatedBody() {
        if (trim($this->body) !== '') {
            $this->resetErrorBag('body');
        }
    }

    public function updatedVehicleId() {
        if ($this->vehicle_id) {
            $this->resetErrorBag('vehicle_id');
        }
    }

    public function postPreview() {
        $this->is_post_preview = false;
        $this->is_post_preview = true;
    }

    public function openVehicle() {
        if (trim($this->body) === '') {
            $this->resetErrorBag('body');
            $this->addError('body', 'Please add a description before selecting a vehicle.');
            return;
        }

        $this->type = 'rental';
        $this->is_renting = true;
        $this->is_post_preview = true;

        $this->dispatch('scroll-to-target', target: 'vehicle');
    }

    public function openRoute() {
        if (trim($this->body) === '') {
            $this->resetErrorBag('body');
            $this->addError('body', 'Please add a description before selecting a route.');
            return;
        }

        $this->type = 'rental';
        $this->is_renting = true;
        $this->is_post_preview = true;

        $this->dispatch('scroll-to-target', target: 'route');
    }

    public function updatedIsRenting() {
        $this->type = $this->is_renting ? 'rental' : 'announcement';
    }

    #[Computed]
    public function getOperatorVehicles()
    {
        if (auth()->user()->role !== 'operator') {
            return collect();
        }

        return Vehicle::where('user_id', auth()->id())->get();
    }

    #[Computed]
    public function getVehicleTypes()
    {
        if (auth()->user()->role !== 'commuter') {
            return collect();
        }

        return OperatorTicketRate::whereNotNull('vehicle_type')
            ->distinct()
            ->pluck('vehicle_type');
    }

    #[Computed]
    public function selectedVehicle()
    {
        if (! $this->vehicle_id) {
            return null;
        }

        return $this->getOperatorVehicles->firstWhere('id', $this->vehicle_id);
    }

    protected function rules(): array
    {
        $role = auth()->user()->role;

        return [
            'body'          => 'required|string|max:255',
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'image|max:2048',
            'vehicle_type'  => ['nullable', 'string', Rule::requiredIf(fn () => $this->type === 'rental' && $role === 'commuter')],
            'vehicle_id'    => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $this->type === 'rental' && $role === 'operator'),
                Rule::exists('vehicles', 'id')->where('user_id', auth()->id()),
                function ($attribute, $value, $fail) use ($role) {
                    if (! $value || $this->type !== 'rental' || $role !== 'operator') {
                        return;
                    }

                    $hasActivePost = Post::where('user_id', auth()->id())
                        ->where('type', 'rental')
                        ->where('status', 'published')
                        ->where('metadata->vehicle_id', $value)
                        ->exists();

                    if ($hasActivePost) {
                        $fail('This vehicle already has an active rental post. Complete any active transaction before posting it again.');
                    }
                },
            ],
            'seats_offered' => [
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    if (! $value || $this->type !== 'rental' || auth()->user()->role !== 'operator') {
                        return;
                    }

                    $vehicle = $this->selectedVehicle;

                    if ($vehicle && $value > $vehicle->total_seats) {
                        $fail("Seats offered can't exceed the vehicle's total seats ({$vehicle->total_seats}).");
                    }
                },
            ],
            'from' => 'nullable|string',
            'to'   => 'nullable|string',
        ];
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

        $metadata = [
            'from'        => $validated_attributes['from'],
            'to'          => $validated_attributes['to'],
            'attachments' => $storedAttachments,
        ];

        if ($this->type === 'rental' && auth()->user()->role === 'operator') {
            $vehicle = $this->selectedVehicle;

            $metadata['vehicle_id']     = $validated_attributes['vehicle_id'] ?? null;
            $metadata['vehicle_type']   = $vehicle->vehicle_type ?? null;
            $metadata['plate_number']   = $vehicle->plate_number ?? null;
            $metadata['driver_name']    = $vehicle->driver_name ?? null;
            $metadata['total_seats']    = $vehicle->total_seats ?? null;
            $metadata['seats_offered']  = $validated_attributes['seats_offered'] ?? null;
        } elseif ($this->type === 'rental' && auth()->user()->role === 'commuter') {
            $metadata['vehicle_type'] = $validated_attributes['vehicle_type'] ?? null;
        }

        $post = app(PostService::class)->createPost([
            'user_id'  => auth()->id(),
            'type'     => $this->type,
            'body'     => $validated_attributes['body'],
            'status'   => 'published',
            'metadata' => $metadata,
        ]);

        if($post) {
            \Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Posted successfully!',
                text: 'Your post has been published successfully.',
            );

            $this->dispatch('new-post-created');
        }

        $this->reset(['attachments', 'body', 'type', 'is_renting', 'vehicle_type', 'vehicle_id', 'seats_offered', 'is_post_preview']);
        $this->resetValidation();

        // reset() restores class-declared defaults (type = 'announcement',
        // is_renting = false) regardless of role. Re-apply role defaults so
        // operators/commuters stay in rental mode after publishing.
        $this->applyRoleDefaults();
    }

    public function removeAttachment($index)
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }
};
?>

<div
    x-on:scroll-to-target.window="
        $nextTick(() => {
            const el = $el.querySelector('[data-scroll-target=\'' + $event.detail.target + '\']');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        })
    "
>
    <x-card class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm">
        <div class="flex items-start gap-3">
            <x-avatar size="sm" name="{{ auth()->user()->name }}" />

            <div class="flex-1 min-w-0">
                <x-input
                    wire:model.live="body"
                    placeholder="{{ in_array(auth()->user()->role, ['admin', 'cashier']) ? 'Post announcements' : 'Post a rental offer or a request — include route, seats and rate.' }}"
                    class="!bg-light-primary dark:!bg-dark-subtle !border-light-bd-default dark:!border-dark-bd-default"
                />

                @error('body')
                    <x-text class="!text-red-600 dark:!text-red-400 mt-1 ml-1" style="font-size: var(--text-timestamp)">{{ $message }}</x-text>
                @enderror

                <div wire:loading wire:target="attachments" class="mt-2 ml-1">
                    <x-text variant="subtle" style="font-size: var(--text-timestamp)">Uploading...</x-text>
                </div>

                @if (!empty($attachments))
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mt-3">
                        @foreach ($attachments as $index => $attachment)
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

                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-3">
                    @if(in_array(auth()->user()->role, ['operator', 'commuter']))
                        <button
                            type="button"
                            wire:click="openVehicle"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                        >
                            <flux:icon.truck class="w-4 h-4" />
                            Vehicle
                        </button>

                        <button
                            type="button"
                            wire:click="openRoute"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                        >
                            <flux:icon.arrow-right class="w-4 h-4" />
                            Route
                        </button>
                    @endif

                    <label
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                    >
                        <flux:icon.photo class="w-4 h-4" />
                        Photo
                        <input
                            type="file"
                            wire:model="attachments"
                            multiple
                            accept="image/*"
                            class="hidden"
                        >
                    </label>

                    <flux:spacer class="hidden sm:block" />

                    <x-button
                        variant="primary"
                        type="button"
                        wire:click="postPreview"
                        wire:loading.attr="disabled"
                        wire:target="publish"
                        class="!inline-flex !flex-row !flex-nowrap !items-center !justify-center !gap-1.5 !whitespace-nowrap !rounded-full !w-full sm:!w-auto !h-auto !min-w-0 !px-4 !py-2 !leading-none !bg-[color:var(--color-primary)] hover:!bg-[color:var(--color-primary-hover)] !text-white"
                    >
                        <flux:icon.paper-airplane class="!inline-block !w-4 !h-4 !shrink-0 !align-middle" />
                        <span class="!inline-block !leading-none !align-middle">Post</span>
                    </x-button>
                </div>
            </div>
        </div>
    </x-card>

    <flux:modal 
        wire:model="is_post_preview" 
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
        wire:key="preview-modal-{{ count($attachments) }}"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            @if ($this->body)
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                            Preview your post
                        </flux:heading>
                        <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            Review the details before publishing.
                        </flux:text>
                    </div>

                    <flux:modal.close>
                        <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                            <flux:icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </flux:modal.close>
                </div>

                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Post description
                    </flux:label>
                    <flux:textarea 
                        wire:model.live="body" 
                        placeholder="Description"
                        class="mt-1"
                        rows="3"
                    />
                    <flux:error name="body" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                        Attachments
                    </flux:label>
                    <div class="flex flex-wrap items-center gap-3 mt-1">
                        <label
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                        >
                            <flux:icon.photo class="w-4 h-4" />
                            Choose photos
                            <input
                                type="file"
                                wire:model="attachments"
                                multiple
                                accept="image/*"
                                class="hidden"
                            />
                        </label>
                        <div wire:loading wire:target="attachments" class="text-timestamp text-light-txt-muted">
                            Uploading...
                        </div>
                    </div>
                    <flux:error name="attachments" />
                    <flux:error name="attachments.*" />
                    <div wire:key="attachments-preview-{{ count($attachments) }}" class="mt-2">
                        @if (!empty($attachments))
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                @foreach ($attachments as $index => $attachment)
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
                    </div>
                </flux:field>

                @if(auth()->user()->role === 'admin')
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Post type
                        </flux:label>
                        <flux:callout
                            variant="secondary"
                            icon="information-circle"
                            heading="If the 'Renting' switch is turned off, the post type will default to an announcement. Otherwise, it will be treated as a renting type."
                            class="mt-1 mb-2"
                        />
                        <flux:switch wire:model.live="is_renting" label="Renting" align="left" />
                    </flux:field>
                @endif

                <div>
                    @if ($type === 'rental' && auth()->user()->role === 'operator')
                        <div class="space-y-4">
                            @if ($this->getOperatorVehicles->isNotEmpty())
                                <flux:field data-scroll-target="vehicle">
                                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                        Vehicle
                                    </flux:label>
                                    <flux:select wire:model.live="vehicle_id">
                                        <flux:select.option value="">Select a vehicle from your fleet</flux:select.option>
                                        @foreach ($this->getOperatorVehicles as $vehicle)
                                            <flux:select.option 
                                                value="{{ $vehicle->id }}"
                                                wire:key="vehicle-id-{{ $vehicle->id }}"
                                            >
                                                {{ $vehicle->vehicle_type }}@if($vehicle->plate_number) ({{ $vehicle->plate_number }})@endif
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="vehicle_id" />
                                    @if ($this->selectedVehicle)
                                        @php($vehicle = $this->selectedVehicle)
                                        @if (!$vehicle->has_or_cr || ($vehicle->or_cr_expiry_date && \Illuminate\Support\Carbon::parse($vehicle->or_cr_expiry_date)->isPast()) || !$vehicle->has_franchise || ($vehicle->franchise_expiry_date && \Illuminate\Support\Carbon::parse($vehicle->franchise_expiry_date)->isPast()))
                                            <div class="mt-3 rounded-lg border border-light-bd-default dark:border-dark-bd-default p-3 space-y-2">
                                                @if (!$vehicle->has_or_cr || ($vehicle->or_cr_expiry_date && \Illuminate\Support\Carbon::parse($vehicle->or_cr_expiry_date)->isPast()))
                                                    <flux:callout variant="warning" icon="exclamation-triangle" heading="This vehicle's OR/CR is missing or expired." />
                                                @endif
                                                @if (!$vehicle->has_franchise || ($vehicle->franchise_expiry_date && \Illuminate\Support\Carbon::parse($vehicle->franchise_expiry_date)->isPast()))
                                                    <flux:callout variant="warning" icon="exclamation-triangle" heading="This vehicle's franchise is missing or expired." />
                                                @endif
                                            </div>
                                        @endif
                                        <div class="mt-4">
                                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                                Seats offered (optional)
                                            </flux:label>
                                            <x-input
                                                wire:model="seats_offered"
                                                type="number"
                                                min="1"
                                                max="{{ $vehicle->total_seats }}"
                                                placeholder="Up to {{ $vehicle->total_seats }} seats"
                                                class="mt-1"
                                            />
                                            <flux:error name="seats_offered" />
                                        </div>
                                    @endif
                                </flux:field>
                            @else
                                <x-text variant="subtle" style="font-size: var(--text-table-row)">Add a vehicle to your fleet to offer it for rent.</x-text>
                            @endif
                            <flux:field data-scroll-target="route">
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    Destination range (optional)
                                </flux:label>
                                <x-inputs-container class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <x-input wire:model="from" placeholder="From" />
                                        <flux:error name="from" />
                                    </div>
                                    <div>
                                        <x-input wire:model="to" placeholder="To" />
                                        <flux:error name="to" />
                                    </div>
                                </x-inputs-container>
                            </flux:field>
                        </div>
                    @endif

                    @if ($type === 'rental' && auth()->user()->role === 'commuter')
                        <div class="space-y-4">
                            <flux:field data-scroll-target="vehicle">
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    Vehicle type
                                </flux:label>
                                @if ($this->getVehicleTypes->isNotEmpty())
                                    <flux:select wire:model="vehicle_type" placeholder="Select vehicle type" class="mt-1">
                                        @foreach ($this->getVehicleTypes as $vehicleType)
                                            <flux:select.option 
                                                value="{{ $vehicleType }}"
                                                wire:key="vehicle-type-{{ $vehicleType }}"
                                            >
                                                {{ $vehicleType }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="vehicle_type" />
                                @else
                                    <x-text variant="subtle" style="font-size: var(--text-table-row)" class="mt-1">No record found. Please contact the admin.</x-text>
                                @endif
                            </flux:field>
                            <flux:field data-scroll-target="route">
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    Destination range (optional)
                                </flux:label>
                                <x-inputs-container class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <x-input wire:model="from" placeholder="From" />
                                        <flux:error name="from" />
                                    </div>
                                    <div>
                                        <x-input wire:model="to" placeholder="To" />
                                        <flux:error name="to" />
                                    </div>
                                </x-inputs-container>
                            </flux:field>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close class="w-full sm:w-auto">
                        <x-button 
                            type="button" 
                            variant="ghost" 
                            class="w-full sm:w-auto justify-center !font-secondary"
                        >
                            Cancel
                        </x-button>
                    </flux:modal.close>
                    <x-button 
                        wire:click="publish" 
                        variant="primary" 
                        class="w-full sm:w-auto justify-center !bg-[color:var(--color-primary)] hover:!bg-[color:var(--color-primary-hover)] !text-white !font-secondary"
                    >
                        Post
                    </x-button>
                </div>
            @else
                <div class="p-4 text-center">
                    <x-text class="font-secondary text-light-txt-muted dark:text-dark-txt-muted">
                        Please fill in the post description first.
                    </x-text>
                </div>
            @endif
        </div>
    </flux:modal>
</div>