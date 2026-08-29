<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\AuditLog;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterAction = '';
    public string $filterChannel = '';

    public $selectedLog;
    public $selectedDeletingLog;
    public bool $showLogModal = false;
    public bool $showDeleteModal = false;

    public function getLogDetail(int $logId) {

        $this->selectedDeletingLog = null;
        $this->showDeleteModal = false;

        $this->selectedLog = AuditLog::with('user')->find($logId);
        $this->showLogModal = true;
    }

    public function confirmDeleteLog(int $logId) {
        $this->selectedDeletingLog = AuditLog::with('user')->find($logId);
        $this->showDeleteModal = true;
    }

    #[Computed]
    public function getAuditLogs() {
        return AuditLog::with('user')
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('username', 'like', '%' . $this->search . '%');
                })->orWhere('subject', 'like', '%' . $this->search . '%');
            })
            ->when($this->filterAction, function ($query) {
                $query->where('action', $this->filterAction);
            })
            ->when($this->filterChannel, function ($query) {
                $query->where('channel', $this->filterChannel);
            })
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    {{-- Page header – consistent with route page --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Audit Logs
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Track all system events and user actions.
            </x-text>
        </div>

        <flux:button
            variant="primary"
            icon="arrow-down-tray"
            size="sm"
            class="font-secondary shrink-0 w-full sm:w-auto justify-center"
        >
            Export logs
        </flux:button>
    </div>

    {{-- Search and filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-4">
        <flux:input
            class="flex-1 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            size="sm"
            icon="magnifying-glass"
            placeholder="Search logs here"
            wire:model.live.debounce.300ms="search"
        />

        <flux:select
            wire:model.live="filterAction"
            size="sm"
            placeholder="Action"
            class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All actions</flux:select.option>
            <flux:select.option value="fare_tap">Fare tap</flux:select.option>
            <flux:select.option value="top_up">Top up</flux:select.option>
            <flux:select.option value="queue_vehicle">Queued</flux:select.option>
            <flux:select.option value="early_depart">Early depart</flux:select.option>
            <flux:select.option value="queue_departed">Departed</flux:select.option>
            <flux:select.option value="fare_failed">Fare failed</flux:select.option>
            <flux:select.option value="login_failed">Login failed</flux:select.option>
            <flux:select.option value="card_issued">Card issued</flux:select.option>
            <flux:select.option value="card_blocked">Card blocked</flux:select.option>
            <flux:select.option value="route_updated">Route updated</flux:select.option>
        </flux:select>

        <flux:select
            wire:model.live="filterChannel"
            size="sm"
            placeholder="Channel"
            class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
        >
            <flux:select.option value="">All channels</flux:select.option>
            <flux:select.option value="Web">Web</flux:select.option>
            <flux:select.option value="RFID">RFID</flux:select.option>
            <flux:select.option value="Scheduler">Scheduler</flux:select.option>
        </flux:select>
    </div>

    {{-- Table – standard card with p-0 and sticky headers --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Actor</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Action</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Date &amp; Time</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Subject</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Channel</flux:table.column>
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getAuditLogs as $log)
                        @php
                            $role = $log->user?->role ?? 'default';

                            $roleColor = match($role) {
                                'commuter' => 'blue',
                                'cashier'  => 'orange',
                                'admin'    => 'red',
                                'operator' => 'violet',
                                default    => 'zinc',
                            };

                            $badge = match($log['action']) {
                                'fare_tap'       => ['label' => 'Fare tap',      'color' => 'blue'],
                                'top_up'         => ['label' => 'Top up',        'color' => 'green'],
                                'queue_vehicle'  => ['label' => 'Queued',        'color' => 'blue'],
                                'early_depart'   => ['label' => 'Early depart',  'color' => 'orange'],
                                'queue_departed' => ['label' => 'Departed',      'color' => 'green'],
                                'fare_failed'    => ['label' => 'Fare failed',   'color' => 'red'],
                                'login_failed'   => ['label' => 'Login failed',  'color' => 'red'],
                                'card_issued'    => ['label' => 'Card issued',   'color' => 'green'],
                                'card_blocked'   => ['label' => 'Card blocked',  'color' => 'red'],
                                'route_updated'  => ['label' => 'Route updated', 'color' => 'violet'],
                                default          => ['label' => $log['action'],  'color' => 'zinc'],
                            };
                        @endphp
                        <flux:table.row :key="$log->id">
                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex flex-col items-center">
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                        {{ $log->user?->name ?? 'Unknown' }}
                                    </span>
                                    <span class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                        {{ $log->user?->username ?? '-' }}
                                    </span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <flux:badge size="sm" color="{{ $badge['color'] }}" class="font-secondary text-badge text-xs">
                                    {{ $badge['label'] }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $log->created_at->format('M j, Y g:i A') }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary max-w-48 truncate">
                                {{ $log->subject }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $log->channel }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-1.5">
                                    <flux:button
                                        wire:click="getLogDetail({{ $log->id }})"
                                        size="sm"
                                        variant="ghost"
                                        class="font-secondary text-xs md:text-table-row !px-2 md:!px-3"
                                    >
                                        View
                                    </flux:button>
                                    <flux:button
                                        wire:click="confirmDeleteLog({{ $log->id }})"
                                        size="sm"
                                        variant="ghost"
                                        class="font-secondary text-xs md:text-table-row !px-2 md:!px-3 !text-danger dark:!text-dark-danger hover:!bg-danger/10 dark:hover:!bg-dark-danger/10"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.document-text class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No audit logs match your current filters.
                                    </x-text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getAuditLogs->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getAuditLogs->links() }}
            </div>
        @endif
    </flux:card>

    {{-- View modal – consistent with route form modal --}}
    <flux:modal wire:model="showLogModal" :closable="false" class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden">
        @if ($this->selectedLog)
            <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                            Log Details
                        </flux:heading>
                        <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            Detailed view of the selected audit log entry.
                        </flux:text>
                    </div>
                    <flux:modal.close>
                        <button
                            type="button"
                            wire:click="$set('showLogModal', false)"
                            class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1"
                        >
                            <flux:icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </flux:modal.close>
                </div>

                {{-- Log details --}}
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Actor</dt>
                        <dd class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                            {{ $selectedLog->user?->name ?? 'Unknown' }}
                            <span class="text-light-txt-muted dark:text-dark-txt-muted">({{ $selectedLog->user?->username ?? '-' }})</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Action</dt>
                        <dd class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                            <flux:badge size="sm" color="{{ $badge['color'] ?? 'zinc' }}" class="font-secondary text-badge text-xs">
                                {{ $badge['label'] ?? $selectedLog->action }}
                            </flux:badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Subject</dt>
                        <dd class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                            {{ $selectedLog->subject }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Channel</dt>
                        <dd class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                            {{ $selectedLog->channel }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Date &amp; Time</dt>
                        <dd class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                            {{ $selectedLog->created_at->format('F j, Y \a\t g:i A') }}
                        </dd>
                    </div>
                    @if ($selectedLog->metadata)
                        <div class="sm:col-span-2">
                            <dt class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Metadata</dt>
                            <dd class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                <pre class="text-xs bg-light-subtle dark:bg-dark-subtle p-2 rounded overflow-auto max-h-32">{{ json_encode($selectedLog->metadata, JSON_PRETTY_PRINT) }}</pre>
                            </dd>
                        </div>
                    @endif
                </dl>

                {{-- Footer with close button --}}
                <div class="flex justify-end pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <flux:button
                            type="button"
                            wire:click="$set('showLogModal', false)"
                            variant="ghost"
                            class="font-secondary"
                        >
                            Close
                        </flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Delete modal --}}
    <flux:modal wire:model="showDeleteModal" :closable="false" class="w-[calc(100%-2rem)] sm:max-w-md mx-auto rounded-xl overflow-hidden">
        @if ($this->selectedDeletingLog)
            <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
                <!-- Header -->
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="xl" class="!font-primary !font-bold text-danger dark:text-dark-danger">
                            Delete Log
                        </flux:heading>
                        <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            Are you sure you want to delete this audit log entry?
                        </flux:text>
                    </div>
                    <flux:modal.close>
                        <button
                            type="button"
                            wire:click="$set('showDeleteModal', false)"
                            class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1"
                        >
                            <flux:icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </flux:modal.close>
                </div>

                {{-- Log summary --}}
                <div class="bg-light-subtle dark:bg-dark-subtle rounded-lg p-4 space-y-2">
                    <p class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                        <span class="font-medium">Actor:</span> {{ $selectedDeletingLog->user?->name ?? 'Unknown' }}
                    </p>
                    <p class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                        <span class="font-medium">Action:</span> {{ $selectedDeletingLog->action }}
                    </p>
                    <p class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                        <span class="font-medium">Subject:</span> {{ $selectedDeletingLog->subject }}
                    </p>
                    <p class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-primary">
                        <span class="font-medium">Date:</span> {{ $selectedDeletingLog->created_at->format('M j, Y g:i A') }}
                    </p>
                </div>

                {{-- Footer with actions --}}
                <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close class="w-full sm:w-auto">
                        <flux:button
                            type="button"
                            wire:click="$set('showDeleteModal', false)"
                            variant="ghost"
                            class="w-full sm:w-auto justify-center font-secondary"
                        >
                            Cancel
                        </flux:button>
                    </flux:modal.close>
                    <flux:button
                        type="button"
                        variant="danger"
                        icon="trash"
                        wire:click="deleteLog({{ $selectedDeletingLog->id }})"
                        wire:loading.attr="disabled"
                        class="font-secondary w-full sm:w-auto justify-center"
                    >
                        Delete permanently
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>