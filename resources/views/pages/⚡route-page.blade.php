<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

use App\Models\RouteList;
use App\Models\OperatorTicketRate;

use Flux\Flux;

new class extends Component
{
    use WithPagination;

    #[Validate('required|string')]
    public $terminal = "";

    #[Validate('required')]
    public $operator_ticket_rate_id = "";

    #[Validate('required|string')]
    public $first_trip = "";

    #[Validate('required|string')]
    public $last_trip = "";

    #[Validate('required|numeric')]
    public ?float $fare = null;

    // Set when editing an existing route; null while adding a new one.
    // save() branches on this so the same modal + method handle both flows.
    public $editingRouteId = null;

    public $search = "";
    public $vehicleFilter = "";

    // Which section is active: all | local | manila
    public $tab = 'all';

    public function setTab($tab)
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function resetForm()
    {
        $this->reset(['terminal', 'operator_ticket_rate_id', 'first_trip', 'last_trip', 'fare', 'editingRouteId']);
        $this->resetErrorBag();
    }

    public function edit($id)
    {
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return;
        }

        $route = RouteList::findOrFail($id);

        $this->editingRouteId          = $route->id;
        $this->terminal                = $route->terminal;
        $this->operator_ticket_rate_id = $route->operator_ticket_rate_id;
        $this->first_trip              = $route->metadata['first_trip'] ?? '';
        $this->last_trip               = $route->metadata['last_trip'] ?? '';
        $this->fare                    = $route->fare;
    }

    public function save()
    {
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return;
        }

        $validated_attribute = $this->validate();

        $payload = [
            'operator_ticket_rate_id' => (int) $validated_attribute['operator_ticket_rate_id'],
            'terminal'                =>  $validated_attribute['terminal'],
            'fare'                    =>  $validated_attribute['fare'],
            'metadata'                =>  [
                'first_trip'          =>    $validated_attribute['first_trip'],
                'last_trip'           =>    $validated_attribute['last_trip'],
            ],
        ];

        $isEditing = (bool) $this->editingRouteId;

        if ($this->editingRouteId) {
            RouteList::where('id', $this->editingRouteId)->update($payload);
        } else {
            RouteList::create($payload);
        }

        $this->resetForm();
        unset($this->getRouteList);
        $this->dispatch('route-saved');

        Flux::toast(
            variant: 'success',
            heading: $isEditing ? 'Route updated' : 'Route added',
            duration: 3000,
            text: $isEditing
                ? 'Your changes were saved successfully.'
                : 'The new route is now visible on this page.'
        );
    }

    public function delete($id)
    {
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return;
        }

        $route = RouteList::find($id);
        $terminalName = $route?->terminal;

        RouteList::where('id', $id)->delete();
        unset($this->getRouteList);

        Flux::toast(
            variant: 'success',
            heading: 'Route deleted',
            duration: 3000,
            text: $terminalName ? "The route for {$terminalName} was removed." : 'The route was removed.'
        );
    }

    // ─── NEW: Clear validation errors on blur for modal fields ───
    public function updated($property)
    {
        if (in_array($property, ['terminal', 'operator_ticket_rate_id', 'first_trip', 'last_trip', 'fare'])) {
            $this->resetValidation($property);
        }
    }

    #[Computed]
    public function getOperatorTicketRate()
    {
        return OperatorTicketRate::get(['id', 'vehicle_type', 'queueing_fee']);
    }

    #[Computed]
    public function getRouteList()
    {
        return RouteList::with('operatorTicketRate')
            ->when($this->search, function ($q) {
                $q->where('terminal', 'like', '%' . $this->search . '%')
                    ->orWhere('fare', 'like', '%' . $this->search . '%')
                    ->orWhere('metadata->first_trip', 'like', '%' . $this->search . '%')
                    ->orWhere('metadata->last_trip', 'like', '%' . $this->search . '%');
            })
            ->when($this->vehicleFilter, function ($q) {
                $q->whereHas('operatorTicketRate', function ($q2) {
                    $q2->where('id', (int) $this->vehicleFilter);
                });
            })
            ->paginate(10);
    }

    // TODO: replace with an `operators` table once Manila-bound bus lines
    // need their own logos / destinations managed from the admin panel.
    // Hardcoded for now so the page ships without over-building the schema.
    #[Computed]
    public function getManilaOperators()
    {
        // Every Manila-bound operator runs buses only, so if the person filters
        // by a non-bus vehicle type, this section has nothing to show.
        if ($this->vehicleFilter) {
            $selectedType = OperatorTicketRate::find($this->vehicleFilter);

            if (!$selectedType || !str_contains(strtolower($selectedType->vehicle_type), 'bus')) {
                return [];
            }
        }

        return [
            ['name' => 'Philtranco', 'url' => 'https://philtrancobus.com/', 'destinations' => ['Cubao', 'PITX', 'Turbina', 'Southwoods']],
            ['name' => 'DLTB Co.', 'url' => 'https://dltb.online/', 'destinations' => ['Cubao', 'PITX', 'Pasay', 'Turbina', 'Buendia', 'Southwoods']],
            ['name' => 'ALPS', 'url' => 'https://alpsthebus.ph/', 'destinations' => ['Cubao', 'PITX', 'Alabang', 'Turbina']],
            ['name' => 'Raymond', 'url' => 'https://raymondbus.ph/', 'destinations' => ['Cubao', 'PITX', 'Turbina', 'Sampaloc']],
            ['name' => 'RMB', 'url' => 'https://www.facebook.com/rmbbets/', 'destinations' => ['Cubao', 'PITX', 'Alabang', 'Pasay', 'Turbina']],
        ];
    }

    public function render()
    {
        if (!auth()->user()) {
            return $this->view()->layout('layouts.public-layout');
        }

        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div x-on:route-saved.window="$flux.modal('route-form').close()">

    @php
        $isAdmin = auth()->check() && auth()->user()->role === 'admin';
        $canSeeQueueFee = auth()->check() && in_array(auth()->user()->role, ['operator', 'cashier', 'admin']);
    @endphp

    @guest
        {{-- Hero (public landing only) --}}
        <div class="relative overflow-hidden bg-primary px-6 py-14 sm:px-10 sm:py-20">
            <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-secondary/10"></div>
            <div class="absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-white/5"></div>

            <div class="relative mx-auto max-w-5xl">
                <h1 class="mt-4 text-3xl font-extrabold text-white sm:text-4xl">Travel Routes & Fare Information</h1>
                <p class="mt-2 max-w-xl text-body text-white/70">
                    Browse available local and provincial routes, first & last trip schedules, and fares before you travel.
                </p>
            </div>
        </div>
    @endguest

    <div class="{{ auth()->guest() ? 'mx-auto max-w-5xl px-6 py-8 sm:px-10' : '' }}">

        @auth
            {{-- Heading with feed style --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                <div>
                    <x-heading
                        size="xl"
                        class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                        style="font-size: var(--text-page-title)"
                    >
                        Travel Routes & Fare Information
                    </x-heading>
                    <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                        Browse local and provincial routes, first & last trip schedules, and fares.
                    </x-text>
                </div>

                @if ($isAdmin)
                    <div class="shrink-0">
                        <flux:modal.trigger name="route-form">
                            <flux:button wire:click="resetForm" icon="plus" variant="primary" class="font-secondary">Add route</flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
            </div>
        @endauth

        {{-- Admin: add / edit route modal --}}
        @if ($isAdmin)
            <flux:modal
                name="route-form"
                :closable="false"
                class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
            >
                {{-- 🔽 Added overflow-y-auto and max-height to make modal scrollable --}}
                <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
                    <!-- Header -->
                    <div class="flex items-start justify-between">
                        <div>
                            <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                                {{ $editingRouteId ? 'Edit route' : 'Add a route' }}
                            </flux:heading>
                            <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $editingRouteId ? 'Changes apply immediately on this page.' : 'New entries appear immediately on this page.' }}
                            </flux:text>
                        </div>
                        <flux:modal.close>
                            <button
                                type="button"
                                wire:click="resetForm"
                                class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1"
                            >
                                <flux:icon name="x-mark" class="w-5 h-5" />
                            </button>
                        </flux:modal.close>
                    </div>

                    <!-- Fields – all changed to wire:model.blur -->
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            City/Municipality
                        </flux:label>
                        <flux:input
                            wire:model.blur="terminal"
                            placeholder="e.g. Nabua"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                        />
                        <flux:error name="terminal" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Vehicle type
                        </flux:label>
                        <flux:select
                            wire:model.blur="operator_ticket_rate_id"
                            placeholder="Choose vehicle type..."
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                        >
                            @foreach ($this->getOperatorTicketRate as $type)
                                <flux:select.option value="{{ $type->id }}">{{ $type->vehicle_type }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="operator_ticket_rate_id" />
                    </flux:field>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                First trip
                            </flux:label>
                            <flux:input
                                wire:model.blur="first_trip"
                                placeholder="e.g. 7:00 am"
                                class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                            />
                            <flux:error name="first_trip" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Last trip
                            </flux:label>
                            <flux:input
                                wire:model.blur="last_trip"
                                placeholder="e.g. 6:00 pm"
                                class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                            />
                            <flux:error name="last_trip" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Fare
                        </flux:label>
                        <flux:input
                            type="number"
                            wire:model.blur="fare"
                            placeholder="0.00"
                            icon="currency-yen"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                        />
                        <flux:error name="fare" />
                    </flux:field>

                    <!-- Footer -->
                    <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                        <flux:modal.close class="w-full sm:w-auto">
                            <flux:button
                                type="button"
                                wire:click="resetForm"
                                variant="ghost"
                                class="w-full sm:w-auto justify-center font-secondary"
                            >
                                Cancel
                            </flux:button>
                        </flux:modal.close>
                        <flux:button
                            type="button"
                            variant="primary"
                            icon="check"
                            wire:click="save"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="font-secondary w-full sm:w-auto justify-center"
                        >
                            {{ $editingRouteId ? 'Save changes' : 'Add route' }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

        {{-- Search + vehicle filter --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <div class="flex-1">
                <flux:input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by destination or fare"
                    class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                    icon="magnifying-glass"
                />
            </div>
            <div class="w-full sm:w-48">
                <flux:select wire:model.live.debounce.300ms="vehicleFilter" class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary">
                    <flux:select.option value="">All vehicle types</flux:select.option>
                    @foreach ($this->getOperatorTicketRate as $type)
                        <flux:select.option value="{{ $type->id }}">{{ $type->vehicle_type }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-6 border-b border-light-bd-default dark:border-dark-bd-default">
            @php
                $tabs = [
                    'all'    => 'All routes',
                    'local'  => 'Local',
                    'manila' => 'Manila-Bound',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    class="-mb-px border-b-2 pb-3 text-nav-item font-medium transition-colors
                        {{ $tab === $key
                            ? 'border-primary text-primary dark:border-secondary dark:text-white'
                            : 'border-transparent text-light-txt-muted hover:text-light-txt-body dark:text-dark-txt-muted dark:hover:text-white' }}"
                >
                    {{ $label }}
                    @if ($key === 'local')
                        <span class="ml-1 text-timestamp text-light-txt-muted dark:text-dark-txt-muted">({{ $this->getRouteList->total() }})</span>
                    @elseif ($key === 'manila')
                        <span class="ml-1 text-timestamp text-light-txt-muted dark:text-dark-txt-muted">({{ count($this->getManilaOperators) }})</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Local routes table --}}
        @if ($tab === 'all' || $tab === 'local')
            <div class="mt-8">
                <h2 class="text-section-heading">Local Routes</h2>

                <div class="mt-3 rounded-xl border border-light-bd-default dark:border-dark-bd-default overflow-hidden">
                    <div class="overflow-x-auto">
                        <flux:table container:class="max-h-160">
                            <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                                <flux:table.column align="center" class="px-2! md:px-4! py-2">City/Municipality</flux:table.column>
                                <flux:table.column align="center" class="px-2 md:px-4 py-2">Vehicle</flux:table.column>
                                <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">First trip</flux:table.column>
                                <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Last trip</flux:table.column>
                                <flux:table.column align="center" class="px-2! md:px-4! py-2">Fare</flux:table.column>
                                @if ($canSeeQueueFee)
                                    <flux:table.column align="center" class="hidden lg:table-cell px-2 md:px-4 py-2">Queue Fee</flux:table.column>
                                @endif
                                @if ($isAdmin)
                                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                                @endif
                            </flux:table.columns>

                            <flux:table.rows>
                                @forelse ($this->getRouteList as $route)
                                    @php
                                        $vehicle = strtolower($route->operatorTicketRate->vehicle_type);
                                        $badgeColor = match (true) {
                                            str_contains($vehicle, 'jeep') => 'yellow',
                                            str_contains($vehicle, 'multicab') => 'purple',
                                            str_contains($vehicle, 'aircon') => 'indigo',
                                            str_contains($vehicle, 'uv') => 'green',
                                            str_contains($vehicle, 'bus') => 'blue',
                                            default => 'zinc',
                                        };
                                    @endphp
                                    <flux:table.row :key="$route->id">
                                        <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                            <x-text class="font-secondary text-xs md:text-table-row font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                                {{ $route->terminal }}
                                            </x-text>
                                        </flux:table.cell>

                                        <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                            <flux:badge color="{{ $badgeColor }}" size="sm" class="font-secondary text-badge text-xs">
                                                {{ $route->operatorTicketRate->vehicle_type }}
                                            </flux:badge>
                                        </flux:table.cell>

                                        <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            {{ $route->metadata['first_trip'] }}
                                        </flux:table.cell>

                                        <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            {{ $route->metadata['last_trip'] }}
                                        </flux:table.cell>

                                        <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row font-semibold text-success dark:text-dark-success">
                                            &#8369; {{ number_format($route->fare, 2) }}
                                        </flux:table.cell>

                                        @if ($canSeeQueueFee)
                                            <flux:table.cell align="center" class="hidden lg:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                                &#8369; {{ number_format($route->operatorTicketRate->queueing_fee ?? 0, 2) }}
                                            </flux:table.cell>
                                        @endif

                                        @if ($isAdmin)
                                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2">
                                                <div class="flex items-center justify-center gap-1">
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="pencil"
                                                        wire:click="edit({{ $route->id }})"
                                                        x-on:click="$flux.modal('route-form').show()"
                                                    />
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="trash"
                                                        wire:click="delete({{ $route->id }})"
                                                        wire:confirm="Delete the route for {{ $route->terminal }}? This can't be undone."
                                                    />
                                                </div>
                                            </flux:table.cell>
                                        @endif
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="{{ 5 + ($canSeeQueueFee ? 1 : 0) + ($isAdmin ? 1 : 0) }}" class="px-2 md:px-4 py-4">
                                            <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                                <flux:icon.map-pin class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                                <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                                    No routes found.
                                                </x-text>
                                                @if ($search)
                                                    <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                                        Try a different search term.
                                                    </x-text>
                                                @elseif ($vehicleFilter)
                                                    <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                                        No routes for that vehicle type yet.
                                                    </x-text>
                                                @endif
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>

                    @if ($this->getRouteList->hasPages())
                        <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                            {{ $this->getRouteList->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Manila-bound operators --}}
        @if (($tab === 'all' || $tab === 'manila') && count($this->getManilaOperators))
            <div class="mt-10">
                <h2 class="text-section-heading">Manila-Bound Routes</h2>

                <div class="mt-3 space-y-3">
                    @foreach ($this->getManilaOperators as $operator)
                        <div class="flex flex-col gap-3 rounded-xl border border-light-bd-default bg-light-secondary p-4 sm:flex-row sm:items-center sm:justify-between dark:border-dark-bd-default dark:bg-dark-secondary">
                            <div class="flex items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary text-nav-item font-bold text-white">
                                    {{ strtoupper(substr($operator['name'], 0, 2)) }}
                                </div>
                                <div>
                                    <x-text class="font-secondary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                        {{ $operator['name'] }}
                                    </x-text>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($operator['destinations'] as $destination)
                                            <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs">
                                                {{ $destination }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <flux:link href="{{ $operator['url'] }}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                                <flux:button variant="primary" icon="bookmark" class="w-full sm:w-auto justify-center">Book now</flux:button>
                            </flux:link>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ── FOOTER ── --}}
    @if (!auth()->user())
        <footer class="bg-light-secondary dark:bg-dark-secondary border-t border-light-bd-default dark:border-dark-bd-default mt-10">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-12">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <img src="{{ asset('images/logo.png') }}" alt="SmartICCT" class="h-7 w-auto">
                            <span class="font-primary text-lg font-bold !text-light-txt-primary dark:!text-dark-txt-primary">
                                SmartICCT
                            </span>
                        </div>
                        <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted !leading-relaxed !max-w-xs">
                            Iriga City Central Terminal's digital ecosystem. Real-time queueing, reloadable cards, and seamless travel.
                        </flux:text>
                    </div>

                    {{-- Quick Links --}}
                    <div>
                        <flux:heading size="xs" class="!font-semibold !uppercase !tracking-wide !mb-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                            Quick Links
                        </flux:heading>
                        <nav class="flex flex-col gap-2.5">
                            <flux:link href="/" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                                Explore
                            </flux:link>
                            <flux:link href="{{ route('live.queue') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                                Queue
                            </flux:link>
                            <flux:link href="{{ route('route') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                                Routes
                            </flux:link>
                        </nav>
                    </div>

                    {{-- Contact & Social --}}
                    <div>
                        <flux:heading size="xs" class="!font-semibold !uppercase !tracking-wide !mb-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                            Contact
                        </flux:heading>

                        <div class="flex flex-col gap-2.5 mb-5">
                            <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                                San Nicolas, Iriga City, Camarines Sur
                            </flux:text>
                            <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                                +63 (54) 456-7890
                            </flux:text>
                        </div>

                        {{-- Social icons – inline SVGs with ! on sizing/colors --}}
                        <div class="flex items-center gap-3">
                            <a href="#" aria-label="Facebook"
                            class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                    <path d="M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/>
                                </svg>
                            </a>
                            <a href="#" aria-label="Instagram"
                            class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                    <path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.21.6 1.76 1.15.55.55.9 1.1 1.15 1.76.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43-.26.66-.6 1.21-1.15 1.76a4.9 4.9 0 0 1-1.76 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.76-1.15 4.9 4.9 0 0 1-1.15-1.76c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.2-1.5.34-1.85.18-.46.4-.79.74-1.14.35-.34.68-.56 1.14-.74.35-.14.88-.3 1.85-.34C8.94 2.01 9.28 2 12 2zm0 1.8c-2.67 0-2.99.01-4.04.06-.97.04-1.5.2-1.85.34-.46.18-.79.4-1.14.74-.34.35-.56.68-.74 1.14-.14.35-.3.88-.34 1.85-.05 1.05-.06 1.37-.06 4.04s.01 2.99.06 4.04c.04.97.2 1.5.34 1.85.18.46.4.79.74 1.14.35.34.68.56 1.14.74.35.14.88.3 1.85.34 1.05.05 1.37.06 4.04.06s2.99-.01 4.04-.06c.97-.04 1.5-.2 1.85-.34.46-.18.79-.4 1.14-.74.34-.35.56-.68.74-1.14.14-.35.3-.88.34-1.85.05-1.05.06-1.37.06-4.04s-.01-2.99-.06-4.04c-.04-.97-.2-1.5-.34-1.85a3.08 3.08 0 0 0-.74-1.14 3.08 3.08 0 0 0-1.14-.74c-.35-.14-.88-.3-1.85-.34-1.05-.05-1.37-.06-4.04-.06zm0 4.59a4.61 4.61 0 1 1 0 9.22 4.61 4.61 0 0 1 0-9.22zm0 1.8a2.81 2.81 0 1 0 0 5.62 2.81 2.81 0 0 0 0-5.62zm5.88-1.99a1.08 1.08 0 1 1-2.16 0 1.08 1.08 0 0 1 2.16 0z"/>
                                </svg>
                            </a>
                            <a href="#" aria-label="Messenger"
                            class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                    <path d="M12 2C6.48 2 2 6.15 2 11.27c0 2.91 1.45 5.5 3.72 7.21V22l3.4-1.87c.91.25 1.87.39 2.88.39 5.52 0 10-4.15 10-9.27S17.52 2 12 2zm1.01 12.49-2.55-2.72-4.97 2.72 5.47-5.8 2.61 2.72 4.91-2.72-5.47 5.8z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <flux:separator class="!my-8 dark:!border-dark-bd-default" />

                <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted !flex !items-center !gap-1.5">
                    <flux:icon name="globe-alt" class="!size-3.5" />
                    2026 Iriga City Central Terminal. SmartICCT
                </flux:text>
            </div>
        </footer>
    @endif
</div>