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

    public $search = "";
    public $vehicleFilter = "";

    public function add() {

        $validated_attribute = $this->validate();

        RouteList::create([
            'operator_ticket_rate_id' => (int) $validated_attribute['operator_ticket_rate_id'],
            'terminal'                =>  $validated_attribute['terminal'],
            'fare'                    =>  $validated_attribute['fare'],
            'metadata'                =>  [
                'first_trip'          =>    $validated_attribute['first_trip'],
                'last_trip'           =>    $validated_attribute['last_trip'],
            ],
        ]);

        unset($this->getRouteList);

    }

    #[Computed]
    public function getOperatorTicketRate() {
        return OperatorTicketRate::get(['id', 'vehicle_type']);
    }

    #[Computed]
    public function getRouteList() {
        return RouteList::with('operatorTicketRate')
        ->when($this->search, function($q) {
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

    public function render() {

        if(!auth()->user()) {
            return $this->view()->layout('layouts.public-layout');
        }

        $role = auth()->user()->role;

        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    <x-pages-heading heading="Routes and fare information" description="Browse available local and provincial routes." />

    <flux:card class="flex items-center justify-between gap-2 mb-4">

        <flux:input wire:model="terminal" placeholder="e.g. Nabua" label="City/Municipality"/>
        
        <flux:field>
            <flux:label>Select Vehicle Type</flux:label>
            <flux:select wire:model="operator_ticket_rate_id" placeholder="Choose vehicle type...">
                @foreach ($this->getOperatorTicketRate as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->vehicle_type }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="operator_ticket_rate_id" />
        </flux:field>
        
        <flux:input wire:model="first_trip" placeholder="e.g. 7:00 am" label="First trip" />
        <flux:input wire:model="last_trip" placeholder="e.g. 6:00 pm" label="Last trip" />
        <flux:input type="number" wire:model="fare" placeholder=" &#8369; 0.00" label="Fare" />

        <flux:button wire:click="add" class="mt-8" variant="primary">Add</flux:button>

    </flux:card>

    <div class="flex items-start justify-between gap-4 mb-4">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Search by destination or operator"
            />
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live.debounce.300ms="vehicleFilter" placeholder="All vehicle types" class="w-48">
                <flux:select.option value="">All</flux:select.option>
                @foreach ($this->getOperatorTicketRate as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->vehicle_type }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select>
                <flux:select.option name="local">Local routes</flux:select.option>
                <flux:select.option name="provincial">Provincial routes</flux:select.option>
            </flux:select>
        </div>

    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>City/Municipality</flux:table.column>
            <flux:table.column>Vehicle</flux:table.column>
            <flux:table.column>First trip</flux:table.column>
            <flux:table.column>Last trip</flux:table.column>
            <flux:table.column>Fare</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->getRouteList as $route)
                <x-table-row>
                    <x-table-cell>{{ $route->terminal }}</x-table-cell>
                    <x-table-cell>{{ $route->operatorTicketRate->vehicle_type }}</x-table-cell>
                    <x-table-cell>{{ $route->metadata['first_trip'] }}</x-table-cell>
                    <x-table-cell>{{ $route->metadata['last_trip'] }}</x-table-cell>
                    <x-table-cell>&#8369; {{ $route->fare }}</x-table-cell>
                </x-table-row>
            @empty
                <flux:table.row>
                    <flux:table.cell>
                        There are no current record
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>