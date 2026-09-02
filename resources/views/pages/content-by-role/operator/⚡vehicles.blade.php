<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Events\UserInfoUpdated;
use Livewire\Attributes\On;

use App\Models\Vehicle;
use App\Models\User;

new #[Layout('layouts.operator-layout')]class extends Component
{
    // ===================== EXPORT MODAL =====================
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    #[Computed]
    public function exportUrl(): string
    {
        return route('operator.vehicles.export', array_filter([
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('operator.vehicles.export', array_filter([
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
            'preview'     => 1,
        ]));
    }

    #[Computed]
    public function vehicles() {
        return Vehicle::with(['route_list', 'queue' => function($q) {
            $q->latest();
        }])
        ->where('user_id', auth()->id())
        ->get();
    }

    #[Computed]
    public function vehicleStats(): array {
        $vehicles = $this->vehicles;
        return [
            'total'     => $vehicles->count(),
            'loading'   => $vehicles->filter(fn($vehicle) => $vehicle->queue?->status === 'loading')->count(),
            'staging'   => $vehicles->filter(fn($vehicle) => $vehicle->queue?->status === 'staging')->count(),
            'departed'  => $vehicles->filter(fn($vehicle) => $vehicle->queue?->status === 'departed')->count(),
            'not_queue' => $vehicles->filter(fn($vehicle) => !$vehicle->queue || !in_array($vehicle->queue->status, ['loading', 'staging', 'departed']))->count(),
        ];
    }


    #[On('echo:user-info-updated,UserInfoUpdated')]
    public function refreshUserInfo() {

        unset($this->vehicles);
    }

    // public function mount() {
    //     dd($this->vehicles);
    // }
}
?>

<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <x-pages-heading
            heading="My vehicles"
            description="Monitor your vehicles and their current queue status here."
        />

        <flux:modal.trigger name="export-fleet">
            <flux:button
                variant="primary"
                icon="arrow-down-tray"
                size="sm"
                class="font-secondary shrink-0 w-full sm:w-auto justify-center"
            >
                Export fleet PDF
            </flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Stats cards – same pattern as other pages --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 mb-5">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total vehicles
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->vehicleStats['total'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.check-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Currently loading
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                {{ $this->vehicleStats['loading'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.clock class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    In staging
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                {{ $this->vehicleStats['staging'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-light-subtle dark:bg-dark-subtle shrink-0">
                    <flux:icon.x-mark class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-light-txt-muted dark:text-dark-txt-muted" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Not in queue
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->vehicleStats['not_queue'] }}
            </x-text>
        </flux:card>
    </div>

    {{-- Table – standard card with p-0 and sticky headers --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">#</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Plate no.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Type</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Engine no.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Body no.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Chassis no.</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Route</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Validity date of franchise</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Queue status</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Registered</flux:table.column>
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->vehicles as $index => $vehicle)
                        <flux:table.row :key="$vehicle->id">
                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $index + 1 }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-mono font-medium text-xs md:text-table-row text-light-txt-primary dark:text-dark-txt-primary">
                                {{ $vehicle->plate_number }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $vehicle->vehicle_type }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $vehicle->engine_number ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $vehicle->body_number ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                {{ $vehicle->chassis_number ?? '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                Iriga → {{ $vehicle->route_list->terminal }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if($vehicle->has_franchise && $vehicle->franchise_expiry_date)
                                    <flux:tooltip content="Franchise verified">
                                        <span class="inline-flex items-center gap-1 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                                            <flux:icon.check-circle class="w-4 h-4 text-success dark:text-dark-success" />
                                            {{ $vehicle->franchise_expiry_date->format('M d, Y') }}
                                        </span>
                                    </flux:tooltip>
                                @else
                                    <flux:tooltip content="Franchise not verified">
                                        <flux:icon.x-circle class="w-4 h-4 text-danger dark:text-dark-danger inline" />
                                    </flux:tooltip>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($vehicle->queue?->status === 'loading')
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Loading</flux:badge>
                                @elseif ($vehicle->queue?->status === 'staging')
                                    <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Staging</flux:badge>
                                @elseif ($vehicle->queue?->status === 'waiting')
                                    <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs">Waiting</flux:badge>
                                @elseif ($vehicle->queue?->status === 'departed')
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Departed</flux:badge>
                                @else
                                    <flux:badge color="gray" size="sm" class="font-secondary text-badge text-xs">Not in Queue</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $vehicle->created_at->format('M d, Y') }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2">
                                <flux:link href="/operator/vehicles/{{ $vehicle->id }}" variant="subtle" wire:navigate>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" class="scale-75 md:scale-100" />
                                </flux:link>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.truck class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No vehicles registered yet.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    {{-- ===================== EXPORT MODAL ===================== --}}
    <flux:modal
        name="export-fleet"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export fleet PDF
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        A summary of all vehicles registered under your account.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <div class="flex gap-2">
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Paper size</flux:label>
                    <flux:select wire:model.live="exportPaper" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="letter">Letter</flux:select.option>
                        <flux:select.option value="legal">Legal</flux:select.option>
                        <flux:select.option value="a4">A4</flux:select.option>
                    </flux:select>
                </flux:field>
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Orientation</flux:label>
                    <flux:select wire:model.live="exportOrientation" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="portrait">Portrait</flux:select.option>
                        <flux:select.option value="landscape">Landscape</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('export-fleet').close(); Flux.modal('preview-fleet').show()"
                    icon="eye"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Preview
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== PREVIEW MODAL ===================== --}}
    <flux:modal
        name="preview-fleet"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-3xl mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-4">
            <div class="flex items-start justify-between">
                <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Preview
                </flux:heading>
                <button
                    type="button"
                    x-on:click="Flux.modal('preview-fleet').close()"
                    class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1"
                >
                    <flux:icon name="x-mark" class="w-5 h-5" />
                </button>
            </div>

            <iframe
                wire:key="{{ $this->exportPreviewUrl }}"
                src="{{ $this->exportPreviewUrl }}"
                class="w-full h-[60vh] rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-white"
            ></iframe>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('preview-fleet').close(); Flux.modal('export-fleet').show()"
                    variant="ghost"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    Back to filters
                </flux:button>
                <flux:button
                    href="{{ $this->exportUrl }}"
                    icon="arrow-down-tray"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Download PDF
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>