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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <x-pages-heading heading="Audit Logs" description="Track all system events and user actions." />

        <flux:button variant="primary" icon="arrow-down-tray" size="sm" class="font-secondary shrink-0">
            Export logs
        </flux:button>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-4">
        <flux:input
            class="flex-1 font-secondary text-table-row"
            size="sm"
            icon="magnifying-glass"
            placeholder="Search logs here"
            wire:model.live.debounce.300ms="search"
        />

        <flux:select wire:model.live="filterAction" size="sm" placeholder="Action" class="w-full sm:w-40 font-secondary text-table-row">
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

        <flux:select wire:model.live="filterChannel" size="sm" placeholder="Channel" class="w-full sm:w-40 font-secondary text-table-row">
            <flux:select.option value="">All channels</flux:select.option>
            <flux:select.option value="Web">Web</flux:select.option>
            <flux:select.option value="RFID">RFID</flux:select.option>
            <flux:select.option value="Scheduler">Scheduler</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="mb-4">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
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
                                <div class="flex items-center justify-center gap-1">
                                    <flux:button
                                        wire:click="getLogDetail({{ $log->id }})"
                                        size="sm"
                                        variant="ghost"
                                        icon="eye"
                                        inset="top bottom"
                                        class="scale-75 md:scale-100"
                                        aria-label="View log"
                                    />
                                    <flux:button
                                        wire:click="confirmDeleteLog({{ $log->id }})"
                                        size="sm"
                                        variant="ghost"
                                        icon="trash"
                                        inset="top bottom"
                                        class="scale-75 md:scale-100"
                                        aria-label="Delete log"
                                    />
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

    <flux:modal wire:model="showLogModal" class="w-full max-w-2xl">
        @if ($this->selectedLog)
            <livewire:pages::content-by-role.admin.audit-log-modal
                :selectedLog="$selectedLog"
                :key="'view-' . $selectedLog->id"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="min-w-96">
        @if ($this->selectedDeletingLog)
            <livewire:pages::content-by-role.admin.audit-log-destroy
                :selectedDeletingLog="$selectedDeletingLog"
                :key="'delete-' . $selectedDeletingLog->id"
            />
        @endif
    </flux:modal>
</div>