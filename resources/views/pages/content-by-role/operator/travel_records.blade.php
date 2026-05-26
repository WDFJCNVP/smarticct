<x-layouts::dashboard.operator.operator-dashboard>
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>My Vehicles</flux:breadcrumbs.item>
            <flux:breadcrumbs.item >{{ $vehicle->vehicle_type }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>
    <div class="mt-8">
        <flux:heading size="xl">Vehicle Information</flux:heading>
        <flux:subheading>Viewing details for 
            <strong>{{ $vehicle->vehicle_type }} </strong> 
                with the plate number of 
            <strong>{{ $vehicle->plate_number }}</strong>
        </flux:subheading>
    </div>

    <livewire:pages::content-by-role.operator.queueing_records :vehicle_type="$vehicle->vehicle_type" :plate_number="$vehicle->plate_number" />

</x-layouts::dashboard.operator.operator-dashboard>