<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use Flux\Flux;

use App\Models\Queue;
use App\Models\Vehicle;
use App\Enums\RentalStatus;

new  #[Layout('layouts.operator-layout')] class extends Component
{
    use WithPagination, WithFileUploads;

    public $vehicle_type;
    public $plate_number;
    public $search = '';
    public $sortBy = 'latest';

    public Vehicle $vehicle;

    // Mirrors vehicles.rental_status for the Active / Not active select.
    public string $rentalStatus = 'inactive';

    // ---- vehicle photos (max 5 per vehicle, stored on the "public" disk) ----
    #[Locked]
    public int $maxImages = 5;

    /** Files picked in the browser, saved right away by updatedNewImages(). */
    public array $newImages = [];

    /** @return list<array{path: string, url: string}> */
    #[Computed]
    public function photos(): array
    {
        return collect($this->vehicle->images ?? [])
            ->map(fn ($path) => ['path' => $path, 'url' => asset('storage/' . $path)])
            ->values()
            ->all();
    }

    /** Runs as soon as the operator picks files. */
    public function updatedNewImages(): void
    {
        abort_if($this->vehicle->user_id !== auth()->id(), 404);

        $this->resetErrorBag();

        $vehicle   = $this->vehicle->fresh();
        $existing  = $vehicle->images ?? [];
        $remaining = $this->maxImages - count($existing);

        if ($remaining <= 0) {
            $this->reset('newImages');
            $this->addError('newImages', "This vehicle already has {$this->maxImages} photos. Remove one to add another.");
            return;
        }

        if (count($this->newImages) > $remaining) {
            $this->reset('newImages');
            $this->addError('newImages', "You can add only {$remaining} more " . ($remaining === 1 ? 'photo' : 'photos') . " (maximum {$this->maxImages} per vehicle).");
            return;
        }

        try {
            $this->validate([
                'newImages'   => ['array'],
                'newImages.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ], [
                'newImages.*.image' => 'Only image files can be uploaded.',
                'newImages.*.mimes' => 'Photos must be JPG, PNG or WEBP.',
                'newImages.*.max'   => 'Each photo must be 4 MB or smaller.',
            ]);
        } catch (ValidationException $e) {
            $this->reset('newImages');
            throw $e;
        }

        $saved = [];
        foreach ($this->newImages as $file) {
            $saved[] = $file->store("vehicle-images/{$vehicle->id}", 'public');
        }

        $vehicle->images = array_values(array_merge($existing, $saved));
        $vehicle->save();

        $this->vehicle = $vehicle;
        $this->reset('newImages');
        unset($this->photos);

        $this->notify(
            count($saved) === 1 ? 'Photo added' : 'Photos added',
            'Passengers can see them when they look at this vehicle.',
        );
    }

    public function removeImage(string $path): void
    {
        abort_if($this->vehicle->user_id !== auth()->id(), 404);

        $vehicle = $this->vehicle->fresh();
        $images  = $vehicle->images ?? [];

        // Only paths that belong to this vehicle can be removed.
        if (! in_array($path, $images, true)) {
            return;
        }

        Storage::disk('public')->delete($path);

        $vehicle->images = array_values(array_diff($images, [$path]));

        // If this photo was the vehicle's picture, fall back to the default icon.
        if ($vehicle->profile_image === $path) {
            $vehicle->profile_image = null;
        }

        $vehicle->save();

        $this->vehicle = $vehicle;
        unset($this->photos);

        $this->notify('Photo removed', 'The photo was deleted from this vehicle.');
    }

    /** True until the operator has set up at least one rental event for this vehicle. */
    #[Computed]
    public function hasNoRentalRates(): bool
    {
        return ! $this->vehicle->eventRates()->offered()->exists();
    }

    /** Runs when the Active / Not active select changes. */
    public function updatedRentalStatus(string $value): void
    {
        abort_if($this->vehicle->user_id !== auth()->id(), 404);

        $vehicle = $this->vehicle->fresh();
        $current = $vehicle->rental_status?->value ?? RentalStatus::Inactive->value;
        $target  = RentalStatus::tryFrom($value);

        // Operators can only switch between active and inactive.
        if (! in_array($target, [RentalStatus::Active, RentalStatus::Inactive], true)) {
            $this->rentalStatus = $current;
            return;
        }

        // Suspended is controlled by the admin only.
        if ($vehicle->rental_status === RentalStatus::Suspended) {
            $this->rentalStatus = $current;
            $this->notify('Rental suspended', 'The admin suspended rental for this vehicle, so you cannot change its status.', 'danger');
            return;
        }

        if ($target === RentalStatus::Active) {
            $problems = $this->activationProblems($vehicle);

            if ($problems) {
                $this->rentalStatus = $current;
                $this->notify('Cannot set to active', implode(' ', $problems), 'danger');
                return;
            }
        }

        $vehicle->rental_status = $target;
        $vehicle->save();
        $this->vehicle = $vehicle;

        $this->notify(
            $target === RentalStatus::Active ? 'Vehicle is now active' : 'Vehicle is now not active',
            $target === RentalStatus::Active
                ? 'Passengers can now see it on the booking page.'
                : 'It is hidden from passengers. Existing requests are not affected.',
        );
    }

    /** @return list<string> reasons this vehicle cannot be set to active yet */
    private function activationProblems(Vehicle $vehicle): array
    {
        $problems = [];

        if (auth()->user()->card === null) {
            $problems[] = 'You need an issued card before you can rent out a vehicle.';
        }

        $franchiseOk = $vehicle->has_franchise
            && in_array($vehicle->documentStatus(), ['valid', 'expiring'], true);

        if (! $franchiseOk) {
            $problems[] = 'This vehicle needs a valid franchise.';
        }

        if (! $vehicle->eventRates()->offered()->exists()) {
            $problems[] = 'Set up at least one rental event first.';
        }

        return $problems;
    }

    private function notify(string $heading, string $text, string $variant = 'success'): void
    {
        Flux::toast(
            variant: $variant,   // success | warning | danger
            heading: $heading,
            duration: 4000,
            text: $text,
        );
    }

    public function mount(Vehicle $vehicle)
    {
        abort_if($vehicle->user_id !== auth()->id(), 404);

        $this->vehicle = $vehicle;
        $this->rentalStatus = $vehicle->rental_status?->value ?? RentalStatus::Inactive->value;
    }

    public function updatedSearch() { $this->resetPage(); }
    public function updatedSortBy() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search']);
        $this->sortBy = 'latest';
        $this->resetPage();
    }

    #[Computed]
    public function activeFilterCount()
    {
        return collect([$this->search])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    #[Computed]
    public function getQueuedRecords() {
        return Queue::query()
            ->where('user_id', auth()->user()->id)
            ->where('plate_number', $this->vehicle->plate_number)
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('driver_name', 'like', '%' . $this->search . '%')
                    ->orWhere('status', 'like', '%' . $this->search . '%')
                    ->orWhere('destination', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'oldest'        => $q->oldest(),
                    'driver_asc'    => $q->orderBy('driver_name', 'asc'),
                    'driver_desc'   => $q->orderBy('driver_name', 'desc'),
                    'departed_desc' => $q->orderByDesc('time_departed'),
                    default         => $q->latest(),
                };
            }, fn ($q) => $q->latest())
            ->paginate(10);
    }

    #[Computed]
    public function vehicleStats(): array {
        $all = Queue::where('user_id', auth()->user()->id)->where('plate_number', $this->vehicle->plate_number)->get();
        return [
            'total'      => $all->count(),
            'departed'   => $all->where('status', 'departed')->count(),
            'today'      => $all->filter(fn($queue) => \Carbon\Carbon::parse($queue->time_queued)->isToday())->count(),
        ];
    }

    // public function mount() {
    //     dd($this->getQueuedRecords);
    // }

};
?>
<div>
    <x-page-header
        heading="Vehicle's Record"
    >
    </x-page-header>

    <div class="flex flex-col-reverse sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 mb-6">
        <div>
            <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                All queuing and departure history for this vehicle.
            </x-heading>
        </div>

        <flux:breadcrumbs class="shrink-0 sm:pt-1">
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>
                Back to my vehicles
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Travel records</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- Vehicle summary card --}}
    <flux:card class="mb-5 !p-3 sm:!p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 sm:h-11 sm:w-11 items-center justify-center rounded-lg bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary shrink-0">
                    <flux:icon.truck class="w-4 h-4 sm:w-5 sm:h-5" />
                </div>
                <div>
                    <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                        {{ $this->vehicle->plate_number }}
                    </x-text>
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $this->vehicle->vehicle_type }}</x-text>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 sm:gap-6">
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Body no.</span>
                    <span class="text-sm font-mono font-medium text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->vehicle->body_number ?? '—' }}
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Seats</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->vehicle->total_seats }}
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Operator</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body">
                        {{ $this->vehicle->user->name }}
                    </span>
                </div>
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Franchise</span>
                    @php $status = $this->vehicle->documentStatus(); @endphp
                    @if ($status === 'valid')
                        <span class="text-sm font-medium text-success dark:text-dark-success">Valid</span>
                    @elseif ($status === 'expiring')
                        <span class="text-sm font-medium text-warning dark:text-dark-warning">Expiring</span>
                    @elseif ($status === 'expired')
                        <span class="text-sm font-medium text-danger dark:text-dark-danger">Expired</span>
                    @else
                        <span class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Not verified</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer: franchise (left) and vehicle photos (right) --}}
        <div class="border-t border-light-bd-default dark:border-dark-bd-default mt-3 sm:mt-4 pt-3 sm:pt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 text-xs text-light-txt-muted dark:text-dark-txt-muted">
            {{-- Left --}}
            <div class="flex items-start">
                @if($this->vehicle->has_franchise && $this->vehicle->franchise_expiry_date)
                    <span>Franchise expires: <strong>{{ $this->vehicle->franchise_expiry_date->format('M d, Y') }}</strong></span>
                @else
                    <span class="text-danger dark:text-dark-danger">Franchise not verified</span>
                @endif
            </div>

            {{-- Right: photos --}}
            <div class="sm:justify-self-end w-full sm:w-auto">
                <div class="mb-2 flex items-center justify-between sm:justify-end gap-3">
                    <span class="font-medium text-light-txt-body dark:text-dark-txt-body">Vehicle photos</span>
                    <span class="tabular-nums">{{ count($this->photos) }} / {{ $this->maxImages }}</span>
                </div>

                <div class="flex flex-wrap sm:justify-end gap-2">
                    @foreach ($this->photos as $photo)
                        <div wire:key="photo-{{ md5($photo['path']) }}" class="group relative h-16 w-16 sm:h-20 sm:w-20 shrink-0">
                            <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="block h-full w-full">
                                <img src="{{ $photo['url'] }}" alt="Vehicle photo" loading="lazy"
                                     class="h-full w-full rounded-lg border border-light-bd-default dark:border-dark-bd-default object-cover" />
                            </a>
                            <button
                                type="button"
                                wire:click="removeImage(@js($photo['path']))"
                                wire:confirm="Delete this photo?"
                                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-danger text-white shadow opacity-100 sm:opacity-0 sm:group-hover:opacity-100 focus:opacity-100 transition"
                                aria-label="Delete photo"
                            >
                                <flux:icon.x-mark class="w-3 h-3" />
                            </button>
                        </div>
                    @endforeach

                    @if (count($this->photos) < $this->maxImages)
                        <label
                            wire:key="photo-add"
                            class="flex h-16 w-16 sm:h-20 sm:w-20 shrink-0 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-light-bd-strong dark:border-dark-bd-strong text-light-txt-muted dark:text-dark-txt-muted hover:border-primary hover:text-primary dark:hover:text-dark-txt-primary transition focus-within:ring-2 focus-within:ring-primary"
                        >
                            <input
                                type="file"
                                wire:model="newImages"
                                multiple
                                accept="image/png,image/jpeg,image/webp"
                                class="sr-only"
                            />
                            <span wire:loading.remove wire:target="newImages" class="flex flex-col items-center gap-1">
                                <flux:icon.photo class="w-5 h-5" />
                                <span class="text-[10px] leading-none">Add photo</span>
                            </span>
                            <span wire:loading wire:target="newImages" class="flex flex-col items-center gap-1">
                                <flux:icon.loading class="w-5 h-5" />
                                <span class="text-[10px] leading-none">Uploading</span>
                            </span>
                        </label>
                    @endif
                </div>

                @php $imageError = $errors->first('newImages') ?: $errors->first('newImages.*'); @endphp
                @if ($imageError)
                    <p class="mt-2 text-danger dark:text-dark-danger sm:text-right">{{ $imageError }}</p>
                @else
                    <p class="mt-2 sm:text-right">Up to {{ $this->maxImages }} photos · JPG, PNG or WEBP · 4 MB each</p>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- KPI cards – larger on mobile --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-3 mb-5">
        <flux:card class="flex flex-row items-center justify-between gap-3 sm:flex-col sm:items-start sm:justify-start p-4 sm:p-4">
            <div class="flex items-center gap-2 sm:gap-2">
                <div class="flex items-center justify-center w-7 h-7 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.truck class="w-4 h-4 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-sm sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total trips
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->vehicleStats['total'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-3 sm:flex-col sm:items-start sm:justify-start p-4 sm:p-4">
            <div class="flex items-center gap-2 sm:gap-2">
                <div class="flex items-center justify-center w-7 h-7 sm:w-7 sm:h-7 rounded-lg bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.check-circle class="w-4 h-4 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-sm sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Departed
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold text-danger dark:text-dark-danger">
                {{ $this->vehicleStats['departed'] }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-3 sm:flex-col sm:items-start sm:justify-start p-4 sm:p-4">
            <div class="flex items-center gap-2 sm:gap-2">
                <div class="flex items-center justify-center w-7 h-7 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.clock class="w-4 h-4 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-sm sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Trips today
                </x-text>
            </div>
            <x-text class="font-primary text-xl sm:text-stat-value font-bold text-warning dark:text-dark-warning">
                {{ $this->vehicleStats['today'] }}
            </x-text>
        </flux:card>
    </div>

    {{-- ====== SEARCH & SORT (inline) ====== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        {{-- Search --}}
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search driver name or destination..."
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        {{-- Sort + Clear --}}
        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select
                wire:model.live="sortBy"
                size="sm"
                class="w-full sm:w-52 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="latest">Latest queued</flux:select.option>
                <flux:select.option value="oldest">Oldest queued</flux:select.option>
                <flux:select.option value="driver_asc">Driver (A → Z)</flux:select.option>
                <flux:select.option value="driver_desc">Driver (Z → A)</flux:select.option>
                <flux:select.option value="departed_desc">Recently departed</flux:select.option>
            </flux:select>

            @if ($this->activeFilterCount > 0)
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" class="font-secondary shrink-0">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2! md:px-4! py-2!">Driver</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Destination</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Seats Occupied</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Time queued</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Time departed</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getQueuedRecords as $queue)
                        <flux:table.row :key="$queue->id">
                            <flux:table.cell align="center" class="!px-2 md:!px-4 !py-1.5 md:!py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $queue->driver_name ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $queue->destination }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($queue->status === 'departed')
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Departed</flux:badge>
                                @elseif ($queue->status === 'staging')
                                    <flux:badge color="orange" size="sm" class="font-secondary text-badge text-xs">Staging</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Loading</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="text-xs md:text-timestamp tabular-nums text-light-txt-body dark:text-dark-txt-body">
                                        {{ $queue->seat_count }} / {{ $queue->seat_capacity }}
                                    </span>
                                    <div class="w-12 h-1.5 rounded-full bg-light-subtle dark:bg-dark-subtle overflow-hidden">
                                        <div
                                            class="h-full rounded-full {{ $queue->seat_count >= $queue->seat_capacity ? 'bg-success dark:bg-dark-success' : 'bg-info dark:bg-dark-info' }}"
                                            style="width: {{ $queue->seat_capacity > 0 ? ($queue->seat_count / $queue->seat_capacity * 100) : 0 }}%"
                                        ></div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $queue->time_queued->format('M d, Y') }}<br class="block md:hidden">
                                <span class="text-xs">{{ $queue->time_queued->format('g:i A') }}</span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                @if ($queue->time_departed)
                                    {{ $queue->time_departed->format('M d, Y') }}<br class="block md:hidden">
                                    <span class="text-xs">{{ $queue->time_departed->format('g:i A') }}</span>
                                @else
                                    <span class="text-light-txt-muted/50">—</span>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.archive-box class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No travel records found.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getQueuedRecords->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getQueuedRecords->links() }}
            </div>
        @endif
    </flux:card>
</div>