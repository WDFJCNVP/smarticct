<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\OperatorTicketRate;
use App\Services\AuditLogsService;
use App\Services\BroadcastNotificationService;

new #[Layout('layouts.admin-layout')] class extends Component
{
    // Create form states
    #[Validate('required|string|unique:operator_ticket_rates,vehicle_type')]
    public $vehicle_type ="";

    #[Validate('required|numeric')]
    public $queueing_fee;

    // Edit form states
    public $edit_id;
    public $edit_vehicle_type = "";
    public $edit_queueing_fee;

    public function save() {
        $validated_attributes = $this->validate([
            'vehicle_type' => 'required|string|unique:operator_ticket_rates,vehicle_type',
            'queueing_fee' => 'required|numeric',
        ]);

        OperatorTicketRate::create($validated_attributes);

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Fare Rate Added',
            'subject'  => 'Admin added a queueing fee rate for a vehicle type',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'    => request()->ip(),
                'vehicle_type'  => $validated_attributes['vehicle_type'],
                'queueing_fee'  => $validated_attributes['queueing_fee'],
                'message'       => "Added queueing fee ₱{$validated_attributes['queueing_fee']} for vehicle type \"{$validated_attributes['vehicle_type']}\".",
            ],
        ]);

        app(BroadcastNotificationService::class)->notifyRoles(
            roles: ['operator', 'cashier'],
            type: 'FareRateChanged',
            title: 'New Queueing Fee Added',
            message: "A queueing fee of ₱{$validated_attributes['queueing_fee']} was set for vehicle type \"{$validated_attributes['vehicle_type']}\".",
            metadata: [
                'vehicle_type' => $validated_attributes['vehicle_type'],
                'queueing_fee' => $validated_attributes['queueing_fee'],
            ],
        );

        $this->vehicle_type = "";
        $this->queueing_fee = null;
        unset($this->getOperatorTicket);

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Vehicle type added',
            text: 'The vehicle type and queueing fee have been saved.',
        );
    }

    public function edit($id) {
        $record = OperatorTicketRate::findOrFail($id);
        
        $this->edit_id = $record->id;
        $this->edit_vehicle_type = $record->vehicle_type;
        $this->edit_queueing_fee = $record->queueing_fee;

        $this->modal('edit')->show();
    }

    public function update() {
        $this->validate([
            'edit_vehicle_type' => 'required|string|unique:operator_ticket_rates,vehicle_type,' . $this->edit_id,
            'edit_queueing_fee' => 'required|numeric',
        ]
        );

        $record = OperatorTicketRate::findOrFail($this->edit_id);
        $record->update([
            'vehicle_type' => $this->edit_vehicle_type,
            'queueing_fee' => $this->edit_queueing_fee,
        ]);

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Fare Rate Updated',
            'subject'  => 'Admin updated a queueing fee rate for a vehicle type',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'   => request()->ip(),
                'vehicle_type' => $this->edit_vehicle_type,
                'queueing_fee' => $this->edit_queueing_fee,
                'message'      => "Updated queueing fee to ₱{$this->edit_queueing_fee} for vehicle type \"{$this->edit_vehicle_type}\".",
            ],
        ]);

        app(BroadcastNotificationService::class)->notifyRoles(
            roles: ['operator', 'cashier'],
            type: 'FareRateChanged',
            title: 'Queueing Fee Updated',
            message: "The queueing fee for vehicle type \"{$this->edit_vehicle_type}\" was updated to ₱{$this->edit_queueing_fee}.",
            metadata: [
                'vehicle_type' => $this->edit_vehicle_type,
                'queueing_fee' => $this->edit_queueing_fee,
            ],
        );

        unset($this->getOperatorTicket);
        $this->modal('edit')->close();

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Vehicle type updated',
            text: 'The vehicle type and queueing fee have been updated.',
        );
    }

    #[Computed]
    public function getOperatorTicket() {
        return OperatorTicketRate::get();
    }
};
?>

<div class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Vehicle types and queueing fees')" :subheading="__('Configure the queueing fees for each vehicle type')">   
        <!-- Create Card -->
        <flux:card class="mb-4">
            <x-inputs-container>
                <flux:field>
                    <flux:label>Select Vehicle Type</flux:label>
                    <flux:select wire:model="vehicle_type" placeholder="Choose vehicle type...">
                        <flux:select.option value="Bus">Bus</flux:select.option>
                        <flux:select.option value="UV-express">UV-express</flux:select.option>
                        <flux:select.option value="Multi-cab">Multi-cab</flux:select.option>
                        <flux:select.option value="Jeep">Jeep</flux:select.option>
                    </flux:select>
                    <flux:error name="vehicle_type" />
                </flux:field>

                <flux:input type="number" wire:model="queueing_fee" label="Queueing Fee" placeholder="0.00" /> 
            </x-inputs-container>
            <flux:button wire:click="save" variant="primary" class="w-full cursor-pointer mt-2">Save</flux:button>
        </flux:card>

        <!-- Listing Card -->
        <flux:card class="mt-2">
            <x-table>
                <x-table-columns>
                    <x-table-column>#</x-table-column>
                    <x-table-column>Vehicle type</x-table-column>
                    <x-table-column>Queueing Fee</x-table-column>
                    <x-table-column>Action</x-table-column>
                </x-table-columns>
                <x-table-rows>
                    @forelse ($this->getOperatorTicket as $index => $vehicle)
                        <x-table-row>
                            <x-table-cell>{{ $index + 1 }}</x-table-cell>
                            <x-table-cell>{{ $vehicle->vehicle_type }}</x-table-cell>
                            <x-table-cell> ₱ {{ $vehicle->queueing_fee }}</x-table-cell>
                            <x-table-cell>
                                
                                <flux:button wire:click="edit({{ $vehicle->id }})">Edit</flux:button>
                            </x-table-cell>
                        </x-table-row>
                    @empty
                        <x-table-row>
                            <x-table-cell colspan="4" class="text-center text-light-txt-muted dark:text-dark-txt-muted">There are no current records</x-table-cell>
                        </x-table-row>
                    @endforelse
                </x-table-rows>
            </x-table>
        </flux:card>
    </x-pages::settings.layout>

    <flux:modal name="edit" class="md:w-96">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg">Update Ticket Rate</flux:heading>
                <flux:text class="mt-2">Make changes to the vehicle configuration settings.</flux:text>
            </div>

            <flux:field>
                <flux:label>Select Vehicle Type</flux:label>
                <flux:select wire:model="edit_vehicle_type" placeholder="Choose vehicle type..." size="sm" disabled>
                    <flux:select.option value="Bus">Bus</flux:select.option>
                    <flux:select.option value="UV-express">UV-express</flux:select.option>
                    <flux:select.option value="Multi-cab">Multi-cab</flux:select.option>
                    <flux:select.option value="Jeep">Jeep</flux:select.option>
                </flux:select>
                <flux:error name="edit_vehicle_type" />
            </flux:field>

            <flux:field>
                <flux:input type="number" wire:model="edit_queueing_fee" label="Queueing Fee" placeholder="0.00" size="sm" />
                <flux:error name="edit_queueing_fee" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>