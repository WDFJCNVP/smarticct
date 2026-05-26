<x-layouts::dashboard.operator.operator-dashboard>
    <div>
        <flux:heading size="xl">My Vehicles</flux:heading>
        <flux:text class="mt-2">You can manage your vehicles and monitor it's status here.</flux:text>
    </div>

    <div>
        <div>
            <flux:heading size="lg" class="mt-10 mb-2 flex gap-2 items-center">
                All Vehicles 
                <flux:text class="text-base" size="2xl" variant="subtle">
                    {{ $vehicles->count() }}
                </flux:text>
            </flux:heading>
        </div>
        <div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Plate Number</flux:table.column>
                    <flux:table.column>Vehicle Type</flux:table.column>
                    <flux:table.column>Route</flux:table.column>
                    <flux:table.column>Date Registered</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>

                    @foreach ($vehicles as $vehicle)

                        <flux:table.row>
                        <flux:table.cell>{{$vehicle['plate_number']}}</flux:table.cell>
                        <flux:table.cell>{{$vehicle->vehicle_type}}</flux:table.cell>
                        <flux:table.cell>Iriga Terminal to {{$vehicle->route->terminal->municipality}}</flux:table.cell>
                        <flux:table.cell>{{ $vehicle->created_at->format('M d, Y') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            @if ($vehicle->status === 'loading')
                                <flux:badge color="green" size="sm" inset="top bottom">
                                    in queue
                                </flux:badge>
                            @else
                                <flux:badge color="orange" size="sm" inset="top bottom">
                                    not in queue
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link href="/operator/vehicles/{{$vehicle->id}}" variant="subtle" wire:navigate>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                            </flux:link>
                        </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</x-layouts::dashboard.operator.operator-dashboard>