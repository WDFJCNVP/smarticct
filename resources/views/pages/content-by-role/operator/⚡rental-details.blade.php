<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

use Flux\Flux;

use App\Enums\RentalRequestStatus;
use App\Models\RentalRequest;
use App\Models\Vehicle;

new #[Layout('layouts.operator-layout')] class extends Component
{
    use WithPagination;

    public Vehicle $vehicle;

    public string $search = '';
    public string $statusFilter = '';

    public function mount(Vehicle $vehicle): void
    {
        abort_if($vehicle->user_id !== auth()->id(), 404);

        $this->vehicle = $vehicle;
    }

    // ---- vehicle profile picture (chosen from the vehicle's uploaded photos) ----

    /** @return list<array{path: string, url: string}> every uploaded photo of this vehicle */
    #[Computed]
    public function photos(): array
    {
        return collect($this->vehicle->images ?? [])
            ->map(fn ($path) => ['path' => $path, 'url' => asset('storage/' . $path)])
            ->values()
            ->all();
    }

    /** The chosen picture's path, or null when none is chosen (or the file was deleted since). */
    #[Computed]
    public function profileImagePath(): ?string
    {
        $path = $this->vehicle->profile_image;

        return $path && in_array($path, $this->vehicle->images ?? [], true) ? $path : null;
    }

    #[Computed]
    public function profileImageUrl(): ?string
    {
        return $this->profileImagePath ? asset('storage/' . $this->profileImagePath) : null;
    }

    public function setProfileImage(string $path): void
    {
        abort_if($this->vehicle->user_id !== auth()->id(), 404);

        $vehicle = $this->vehicle->fresh();

        // Only one of this vehicle's own uploaded photos can be chosen.
        if (! in_array($path, $vehicle->images ?? [], true)) {
            Flux::toast(variant: 'danger', heading: 'Photo not found', duration: 4000, text: 'That photo is no longer available. Please pick another one.');
            return;
        }

        $vehicle->profile_image = $path;
        $vehicle->save();

        $this->vehicle = $vehicle;
        unset($this->photos, $this->profileImagePath, $this->profileImageUrl);

        Flux::modal('profile-image')->close();
        Flux::toast(variant: 'success', heading: 'Vehicle picture updated', duration: 4000, text: 'This photo is now the picture of your vehicle.');
    }

    public function clearProfileImage(): void
    {
        abort_if($this->vehicle->user_id !== auth()->id(), 404);

        $vehicle = $this->vehicle->fresh();
        $vehicle->profile_image = null;
        $vehicle->save();

        $this->vehicle = $vehicle;
        unset($this->profileImagePath, $this->profileImageUrl);

        Flux::modal('profile-image')->close();
        Flux::toast(variant: 'success', heading: 'Picture removed', duration: 3000, text: 'The default vehicle icon is shown again.');
    }

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    #[Computed]
    public function activeFilterCount()
    {
        return collect([$this->search, $this->statusFilter])
            ->filter(fn ($v) => filled($v))
            ->count();
    }

    /** Every rate this vehicle has, including ones switched off. */
    #[Computed]
    public function rates()
    {
        return $this->vehicle->eventRates()
            ->with('eventType')
            ->get()
            ->sortBy(fn ($rate) => $rate->eventType->name)
            ->values();
    }

    #[Computed]
    public function requestStats(): array
    {
        $counts = RentalRequest::where('vehicle_id', $this->vehicle->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $n = fn (RentalRequestStatus $s) => (int) ($counts[$s->value] ?? 0);

        return [
            'total'     => (int) $counts->sum(),
            'pending'   => $n(RentalRequestStatus::Pending),
            'awaiting'  => $n(RentalRequestStatus::Quoted) + $n(RentalRequestStatus::AwaitingPayment),
            'confirmed' => $n(RentalRequestStatus::Confirmed),
            'completed' => $n(RentalRequestStatus::Completed),
        ];
    }

    #[Computed]
    public function requests()
    {
        $status = RentalRequestStatus::tryFrom($this->statusFilter);

        return RentalRequest::query()
            ->with('passenger.userStatus')
            ->where('vehicle_id', $this->vehicle->id)
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';

                $q->where(function ($q2) use ($term) {
                    $q2->whereHas('passenger', fn ($p) => $p->where('name', 'like', $term))
                        ->orWhere('event_name', 'like', $term)
                        ->orWhere('pickup_municipality', 'like', $term)
                        ->orWhere('destination_municipality', 'like', $term);
                });
            })
            // Requests waiting for the operator come first, then newest.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [RentalRequestStatus::Pending->value])
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <x-page-header heading="Rental Details"></x-page-header>

    <div class="flex flex-col-reverse sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 mb-6">
        <div>
<!--             <x-heading
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-section-heading)"
            >
                Rental details and passenger requests for {{ $vehicle->plate_number }}.
            </x-heading> -->
        </div>

        <flux:breadcrumbs class="shrink-0 sm:pt-1">
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>
                Back to my vehicles
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Rental details</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- ===================== VEHICLE ===================== --}}
    <flux:card class="mb-5 !p-3 sm:!p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
            <div class="flex items-center gap-3">
                {{-- Vehicle picture (falls back to the truck icon), with a pen button to change it --}}
                <div class="relative shrink-0">
                    @if ($this->profileImageUrl)
                        <img
                            src="{{ $this->profileImageUrl }}"
                            alt="Picture of {{ $vehicle->plate_number }}"
                            class="h-24 w-24 sm:h-28 sm:w-28 rounded-xl border border-light-bd-default dark:border-dark-bd-default object-cover"
                        />
                    @else
                        <div class="flex h-24 w-24 sm:h-28 sm:w-28 items-center justify-center rounded-xl bg-primary/10 dark:bg-primary/20 text-primary dark:text-dark-txt-primary">
                            <flux:icon.truck class="w-9 h-9 sm:w-10 sm:h-10" />
                        </div>
                    @endif

                    <flux:modal.trigger name="profile-image">
                        <button
                            type="button"
                            x-on:click="$dispatch('reset-profile-picker')"
                            class="absolute -bottom-1.5 -right-1.5 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white shadow-md ring-2 ring-white dark:ring-dark-secondary hover:bg-primary-hover transition focus:outline-none focus-visible:ring-primary"
                            aria-label="Change vehicle picture"
                        >
                            <flux:icon.pencil class="w-4 h-4" />
                        </button>
                    </flux:modal.trigger>
                </div>
                <div>
                    <x-text class="font-mono font-medium text-light-txt-primary dark:text-dark-txt-primary text-sm sm:text-base">
                        {{ $vehicle->plate_number }}
                    </x-text>
                    <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $vehicle->vehicle_type }}</x-text>
                </div>
            </div>

            @php
                $rentalStatus = $vehicle->rental_status;
            @endphp
            @if ($rentalStatus === \App\Enums\RentalStatus::Active)
                <flux:badge color="green" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">Active for renting</flux:badge>
            @elseif ($rentalStatus === \App\Enums\RentalStatus::Suspended)
                <flux:badge color="red" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">Suspended by admin</flux:badge>
            @else
                <flux:badge color="zinc" size="sm" class="self-start sm:self-auto font-secondary text-badge text-xs">Not active</flux:badge>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3">
            @foreach ([
                'Seats'      => $vehicle->total_seats,
                'Driver'     => $vehicle->driver_name,
                'Body no.'   => $vehicle->body_number,
                'Engine no.' => $vehicle->engine_number,
                'Chassis no.' => $vehicle->chassis_number,
                'Route'      => $vehicle->route_list?->terminal ? 'Iriga → ' . $vehicle->route_list->terminal : null,
            ] as $label => $value)
                <div>
                    <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">{{ $label }}</span>
                    <span class="text-sm font-medium text-light-txt-body dark:text-dark-txt-body break-words">{{ $value ?: '—' }}</span>
                </div>
            @endforeach

            <div>
                <span class="block text-[10px] sm:text-xs text-light-txt-muted dark:text-dark-txt-muted uppercase tracking-wider mb-0.5">Franchise</span>
                @php $franchise = $vehicle->documentStatus(); @endphp
                @if ($franchise === 'valid')
                    <span class="text-sm font-medium text-success dark:text-dark-success">Valid</span>
                @elseif ($franchise === 'expiring')
                    <span class="text-sm font-medium text-warning dark:text-dark-warning">Expiring</span>
                @elseif ($franchise === 'expired')
                    <span class="text-sm font-medium text-danger dark:text-dark-danger">Expired</span>
                @else
                    <span class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Not verified</span>
                @endif
                @if ($vehicle->has_franchise && $vehicle->franchise_expiry_date)
                    <span class="block text-xs text-light-txt-muted dark:text-dark-txt-muted">until {{ $vehicle->franchise_expiry_date->format('M d, Y') }}</span>
                @endif
            </div>
        </div>
    </flux:card>

    {{-- ===================== RATES ===================== --}}
    <flux:card class="mb-5 !p-3 sm:!p-6">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <x-heading class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary" style="font-size: var(--text-section-heading)">
                    Events and rates
                </x-heading>
                <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    What passengers see for this vehicle.
                </x-text>
            </div>
            <flux:button
                href="{{ route('setup-rental', ['vehicle' => $vehicle->id]) }}"
                wire:navigate
                size="sm"
                variant="ghost"
                icon="pencil-square"
                class="font-secondary shrink-0"
            >
                Edit
            </flux:button>
        </div>

        <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default">
            @forelse ($this->rates as $rate)
                <div wire:key="rate-{{ $rate->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $rate->eventType->name }}</span>
                        @unless ($rate->is_active && $rate->eventType->is_active)
                            <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs">Off</flux:badge>
                        @endunless
                    </div>
                    <div class="text-xs sm:text-sm text-light-txt-body dark:text-dark-txt-body tabular-nums">
                        Down payment: <strong>{{ \App\Support\Peso::format($rate->down_payment_amount) }}</strong>
                    </div>
                </div>
            @empty
                <x-text class="py-3 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    No rental events set up yet.
                </x-text>
            @endforelse
        </div>
    </flux:card>

    {{-- ===================== REQUEST STATS ===================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">New requests</x-text>
            <x-text class="font-primary text-stat-value font-bold block {{ $this->requestStats['pending'] > 0 ? 'text-warning dark:text-dark-warning' : 'text-light-txt-primary dark:text-dark-txt-primary' }}">
                {{ $this->requestStats['pending'] }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Awaiting passenger</x-text>
            <x-text class="font-primary text-stat-value font-bold block text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->requestStats['awaiting'] }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Confirmed</x-text>
            <x-text class="font-primary text-stat-value font-bold block text-success dark:text-dark-success">
                {{ $this->requestStats['confirmed'] }}
            </x-text>
        </flux:card>
        <flux:card class="p-3 sm:p-4">
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">Completed</x-text>
            <x-text class="font-primary text-stat-value font-bold block text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->requestStats['completed'] }}
            </x-text>
        </flux:card>
    </div>

    {{-- ===================== SEARCH & FILTER ===================== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search passenger, event, or town..."
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select
                wire:model.live="statusFilter"
                size="sm"
                placeholder="All statuses"
                class="w-full sm:w-48 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="">All statuses</flux:select.option>
                @foreach (\App\Enums\RentalRequestStatus::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->activeFilterCount > 0)
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" class="font-secondary shrink-0">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    {{-- ===================== REQUESTS ===================== --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Passenger</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Event</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Route</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Trip</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Passengers</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Full Payment</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Action</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->requests as $request)
                        <flux:table.row :key="$request->id">
                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ ($request->passenger && ! $request->passenger->isDeleted()) ? $request->passenger->name : 'Deleted User' }}
                                <span class="block text-[10px] md:text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $request->created_at->diffForHumans() }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $request->event_name }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-body dark:text-dark-txt-body">
                                {{ $request->pickup_municipality }} → {{ $request->destination_municipality }}
                                <span class="block max-w-[16rem] truncate text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $request->pickup_details }} → {{ $request->destination_details }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $request->trip_start_at->format('M d, Y') }}
                                <span class="block text-xs">{{ $request->trip_start_at->format('g:i A') }} – {{ $request->trip_end_at->format($request->trip_end_at->isSameDay($request->trip_start_at) ? 'g:i A' : 'M d, g:i A') }}</span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body tabular-nums">
                                {{ $request->passenger_count }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body tabular-nums">
                                {{ $request->quoted_total !== null ? '₱' . number_format((float) $request->quoted_total, 2) : '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @php
                                    // A request past its deadline already reads "Expired", even before the clean-up job runs.
                                    $lapsed = $request->isLapsed();
                                    $badgeColor = $lapsed ? 'red' : match ($request->status) {
                                        \App\Enums\RentalRequestStatus::Pending         => 'yellow',
                                        \App\Enums\RentalRequestStatus::Quoted          => 'blue',
                                        \App\Enums\RentalRequestStatus::AwaitingPayment => 'orange',
                                        \App\Enums\RentalRequestStatus::Confirmed       => 'green',
                                        \App\Enums\RentalRequestStatus::Completed       => 'zinc',
                                        default                                         => 'red',
                                    };
                                @endphp
                                <flux:badge :color="$badgeColor" size="sm" class="font-secondary text-badge text-xs">
                                    {{ $lapsed ? 'Expired' : $request->status->label() }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <flux:button
                                    href="{{ route('rental-request-review', ['rentalRequest' => $request->id]) }}"
                                    wire:navigate
                                    size="sm"
                                    :variant="($request->status === \App\Enums\RentalRequestStatus::Pending && ! $lapsed) ? 'primary' : 'ghost'"
                                    class="font-secondary"
                                >
                                    {{ ($request->status === \App\Enums\RentalRequestStatus::Pending && ! $lapsed) ? 'Review' : 'View' }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.archive-box class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No rental requests yet.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->requests->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->requests->links() }}
            </div>
        @endif
    </flux:card>

    {{-- ===================== VEHICLE PICTURE MODAL ===================== --}}
    <flux:modal name="profile-image" class="w-[calc(100%-2rem)] md:w-[44rem] md:max-w-2xl mx-auto rounded-xl">
        <div class="flex flex-col gap-4 max-h-[80vh] overflow-y-auto p-1">
            <div>
                <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Choose the vehicle picture
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    Pick one of the photos you uploaded. Passengers will see it for this vehicle.
                </flux:text>
            </div>

            @if (count($this->photos) > 0)
                <div
                    wire:key="profile-picker-{{ md5(($this->profileImagePath ?? '') . '|' . count($this->photos)) }}"
                    x-data="{
                        photos: @js($this->photos),
                        initial: @js($this->profileImagePath),
                        selected: @js($this->profileImagePath ?? $this->photos[0]['path']),
                        get current() { return this.photos.find(p => p.path === this.selected); },
                    }"
                    x-on:reset-profile-picker.window="selected = initial ?? photos[0].path"
                    class="flex flex-col gap-3"
                >
                    {{-- big preview --}}
                    <div class="relative overflow-hidden rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle">
                        <img :src="current?.url" alt="Selected vehicle photo" class="aspect-[4/3] sm:aspect-[16/10] w-full object-contain" />
                        <span
                            x-show="selected === initial" x-cloak
                            class="absolute left-3 top-3 rounded-md bg-primary px-2 py-1 text-xs font-medium text-white shadow"
                        >Current picture</span>
                    </div>

                    {{-- thumbnails --}}
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <template x-for="photo in photos" :key="photo.path">
                            <button
                                type="button"
                                x-on:click="selected = photo.path"
                                :aria-pressed="selected === photo.path"
                                :class="selected === photo.path ? 'ring-2 ring-primary ring-offset-2 ring-offset-white dark:ring-offset-dark-secondary' : 'opacity-70 hover:opacity-100'"
                                class="h-16 w-16 sm:h-20 sm:w-20 shrink-0 overflow-hidden rounded-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            >
                                <img :src="photo.url" alt="" class="h-full w-full object-cover" />
                            </button>
                        </template>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                        <div>
                            @if ($this->profileImagePath)
                                <flux:button
                                    type="button"
                                    wire:click="clearProfileImage"
                                    wire:confirm="Remove the picture and show the default icon?"
                                    variant="ghost" size="sm" icon="trash"
                                    class="w-full sm:w-auto justify-center font-secondary"
                                >Use default icon</flux:button>
                            @endif
                        </div>

                        <div class="flex flex-col-reverse sm:flex-row gap-2">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">Cancel</flux:button>
                            </flux:modal.close>
                            <flux:button
                                type="button"
                                variant="primary"
                                x-on:click="$wire.setProfileImage(selected)"
                                x-bind:disabled="selected === initial"
                                wire:loading.attr="disabled"
                                wire:target="setProfileImage"
                                class="w-full sm:w-auto justify-center font-secondary"
                            >Select this image</flux:button>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <flux:icon.photo class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                    <x-text class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This vehicle has no photos yet. Upload photos from its Travel records page, then come back to choose one.
                    </x-text>
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" class="font-secondary">Close</flux:button>
                    </flux:modal.close>
                </div>
            @endif
        </div>
    </flux:modal>
</div>