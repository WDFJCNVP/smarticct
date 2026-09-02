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

    // ===================== EXPORT MODAL =====================
    public string $exportDateFrom = '';
    public string $exportDateTo = '';
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    // Left blank by default — an audit trail export scoped to "today" would
    // miss most of what someone doing an investigation actually needs, so
    // this starts as "all time" and can be narrowed down from there.
    public function prepareExportModal()
    {
        $this->setExportRangeAllTime();
    }

    public function setExportRangeAllTime()
    {
        $this->exportDateFrom = '';
        $this->exportDateTo = '';
    }

    public function setExportRangeToday()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    #[Computed]
    public function exportRangePreset(): string
    {
        if ($this->exportDateFrom === '' && $this->exportDateTo === '') {
            return 'all';
        }

        $today = today()->toDateString();

        if ($this->exportDateFrom === $today && $this->exportDateTo === $today) {
            return 'today';
        }

        return 'custom';
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('admin.audit.logs.export', array_filter([
            'search'      => $this->search,
            'action'      => $this->filterAction,
            'channel'     => $this->filterChannel,
            'from'        => $this->exportDateFrom,
            'to'          => $this->exportDateTo,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('admin.audit.logs.export', array_filter([
            'search'      => $this->search,
            'action'      => $this->filterAction,
            'channel'     => $this->filterChannel,
            'from'        => $this->exportDateFrom,
            'to'          => $this->exportDateTo,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
            'preview'     => 1,
        ]));
    }

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

    // A few legacy action values (from before this was standardized) are
    // snake_case, e.g. 'login_failed' — everything newer is already a
    // readable "Title Case" string. This normalizes both for display.
    public function formatActionLabel(string $action): string
    {
        if (! str_contains($action, '_')) {
            return $action;
        }

        return ucwords(str_replace('_', ' ', $action));
    }

    #[Computed]
    public function availableActions() {
        return AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');
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

        <flux:modal.trigger name="export-audit-logs" wire:click="prepareExportModal">
            <flux:button
                variant="primary"
                icon="arrow-down-tray"
                size="sm"
                class="font-secondary shrink-0 w-full sm:w-auto justify-center"
            >
                Export logs
            </flux:button>
        </flux:modal.trigger>
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
            @foreach ($this->availableActions as $action)
                <flux:select.option value="{{ $action }}">{{ $this->formatActionLabel($action) }}</flux:select.option>
            @endforeach
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

    {{-- ===================== EXPORT MODAL ===================== --}}
    <flux:modal
        name="export-audit-logs"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export audit logs
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose a date range for the PDF. Defaults to all time, and keeps whatever search/action/channel filters are active above.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Date range</flux:label>

                <div class="flex gap-2 mt-1.5">
                    <button
                        type="button"
                        wire:click="setExportRangeAllTime"
                        class="flex-1 rounded-lg border px-3 py-2 font-secondary text-sm font-medium transition text-center
                            {{ $this->exportRangePreset === 'all'
                                ? 'bg-primary text-white border-primary'
                                : 'bg-transparent text-light-txt-body dark:text-dark-txt-body border-light-bd-default dark:border-dark-bd-default hover:bg-light-subtle dark:hover:bg-dark-subtle' }}"
                    >
                        All Time
                    </button>
                    <button
                        type="button"
                        wire:click="setExportRangeToday"
                        class="flex-1 rounded-lg border px-3 py-2 font-secondary text-sm font-medium transition text-center
                            {{ $this->exportRangePreset === 'today'
                                ? 'bg-primary text-white border-primary'
                                : 'bg-transparent text-light-txt-body dark:text-dark-txt-body border-light-bd-default dark:border-dark-bd-default hover:bg-light-subtle dark:hover:bg-dark-subtle' }}"
                    >
                        Today
                    </button>
                </div>

                <div class="flex items-center gap-2 mt-3">
                    <flux:input
                        type="date"
                        wire:model.live="exportDateFrom"
                        size="sm"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    />
                    <span class="text-light-txt-muted dark:text-dark-txt-muted text-sm shrink-0">to</span>
                    <flux:input
                        type="date"
                        wire:model.live="exportDateTo"
                        size="sm"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    />
                </div>
                <flux:text class="mt-1.5 font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                    Or pick a custom range above.
                </flux:text>
            </flux:field>

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
                    x-on:click="Flux.modal('export-audit-logs').close(); Flux.modal('preview-audit-logs').show()"
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
        name="preview-audit-logs"
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
                    x-on:click="Flux.modal('preview-audit-logs').close()"
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
                    x-on:click="Flux.modal('preview-audit-logs').close(); Flux.modal('export-audit-logs').show()"
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