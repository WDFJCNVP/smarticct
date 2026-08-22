<x-layouts::dashboard.operator.operator-dashboard>
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('operator.vehicles') }}" wire:navigate>My Vehicles</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $vehicle->vehicle_type }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="mt-8">
        <flux:heading size="xl">Vehicle Information</flux:heading>
        <flux:subheading>Viewing details for 
            <strong>{{ $vehicle->vehicle_type }}</strong> 
            with plate number 
            <strong>{{ $vehicle->plate_number }}</strong>
        </flux:subheading>
    </div>

    {{-- Vehicle details card --}}
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <flux:card class="col-span-1">
            <flux:heading size="lg" class="mb-3">General Info</flux:heading>
            <dl class="space-y-2">
                <div class="flex justify-between border-b border-light-bd-default dark:border-dark-bd-default py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Plate number</dt>
                    <dd class="font-mono font-medium">{{ $vehicle->plate_number }}</dd>
                </div>
                <div class="flex justify-between border-b border-light-bd-default dark:border-dark-bd-default py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Vehicle type</dt>
                    <dd>{{ $vehicle->vehicle_type }}</dd>
                </div>
                <div class="flex justify-between border-b border-light-bd-default dark:border-dark-bd-default py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Total seats</dt>
                    <dd>{{ $vehicle->total_seats }}</dd>
                </div>
                <div class="flex justify-between border-b border-light-bd-default dark:border-dark-bd-default py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Route</dt>
                    <dd>Iriga Terminal → {{ $vehicle->route_list->terminal }}</dd>
                </div>
                <div class="flex justify-between border-b border-light-bd-default dark:border-dark-bd-default py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Dedicated driver</dt>
                    <dd>{{ $vehicle->driver_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Registered on</dt>
                    <dd>{{ $vehicle->created_at->format('M d, Y') }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="col-span-1">
            <flux:heading size="lg" class="mb-3">Compliance Documents</flux:heading>
            <dl class="space-y-2">
                <div class="flex justify-between border-b border-light-bd-default dark:border-dark-bd-default py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">OR/CR verified</dt>
                    <dd>
                        @if($vehicle->has_or_cr)
                            <flux:icon.check-circle class="w-4 h-4 text-green-500 inline" />
                            <span class="ml-1 text-sm">{{ $vehicle->or_cr_expiry_date?->format('M d, Y') ?? 'No expiry' }}</span>
                        @else
                            <flux:icon.x-circle class="w-4 h-4 text-red-400 inline" />
                            <span class="ml-1 text-sm text-red-400">Not verified</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between py-1">
                    <dt class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Franchise verified</dt>
                    <dd>
                        @if($vehicle->has_franchise)
                            <flux:icon.check-circle class="w-4 h-4 text-green-500 inline" />
                            <span class="ml-1 text-sm">{{ $vehicle->franchise_expiry_date?->format('M d, Y') ?? 'No expiry' }}</span>
                        @else
                            <flux:icon.x-circle class="w-4 h-4 text-red-400 inline" />
                            <span class="ml-1 text-sm text-red-400">Not verified</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </flux:card>
    </div>

    {{-- Queue records --}}
    <div class="mt-6">
        <livewire:pages::content-by-role.operator.queueing_records 
            :vehicle_type="$vehicle->vehicle_type" 
            :plate_number="$vehicle->plate_number" 
        />
    </div>

</x-layouts::dashboard.operator.operator-dashboard>