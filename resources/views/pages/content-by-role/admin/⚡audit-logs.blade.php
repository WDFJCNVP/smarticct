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
    <x-pages-heading heading="Audit Logs" description="Track all system events and user actions." />

    {{-- Stat cards --}}
    {{-- <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6 mb-6">
        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
            <x-text class="text-xs text-zinc-500 dark:text-zinc-400">Total events</x-text>
            <x-text class="text-2xl font-medium mt-1">1,284</x-text>
        </div>
        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
            <x-text class="text-xs text-zinc-500 dark:text-zinc-400">Failures</x-text>
            <x-text class="text-2xl font-medium mt-1 text-red-600 dark:text-red-400">12</x-text>
        </div>
        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
            <x-text class="text-xs text-zinc-500 dark:text-zinc-400">Manual overrides</x-text>
            <x-text class="text-2xl font-medium mt-1 text-amber-600 dark:text-amber-400">3</x-text>
        </div>
        <div class="bg-zinc-100 dark:bg-zinc-800 rounded-lg p-4">
            <x-text class="text-xs text-zinc-500 dark:text-zinc-400">Today's events</x-text>
            <x-text class="text-2xl font-medium mt-1">87</x-text>
        </div>
    </div> --}}

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex flex-wrap gap-2">
            <flux:select wire:model.live="filterAction" placeholder="Action" class="w-36">
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

            <flux:select wire:model.live="filterChannel" placeholder="Channel" class="w-36">
                <flux:select.option value="">All channels</flux:select.option>
                <flux:select.option value="Web">Web</flux:select.option>
                <flux:select.option value="RFID">RFID</flux:select.option>
                <flux:select.option value="Scheduler">Scheduler</flux:select.option>
            </flux:select>
        </div>

        <div class="flex items-center gap-2">
            <x-input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search logs here"
                icon="magnifying-glass"
            />
            <x-button icon="arrow-down-tray" variant="primary">
                Export logs
            </x-button>
        </div>
    </div>

    <div>
        <x-table>
            <x-table-columns class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                <x-table-column>Actor</x-table-column>
                <x-table-column>Action</x-table-column>
                <x-table-column>Date & Time</x-table-column>
                <x-table-column>Subject</x-table-column>
                <x-table-column>Channel</x-table-column>
                <x-table-column>Actions</x-table-column>
            </x-table-columns>
            <x-table-rows class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->getAuditLogs as $log)
                    @php

                        $role = $log->user?->role ?? 'default';

                        $roleColor = match($role) {
                            'commuter' => "blue",
                            'cashier'  => "orange",
                            'admin'    => "red",
                            'operator' => "violet",
                            default    => null,
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
                            default          => ['label' => $log['action'],  'color' => null],
                        };
                    @endphp
                    <x-table-row class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <x-table-cell class="px-4 py-3">
                            <x-text color="{{ $roleColor }}">{{ $log->user?->name ?? 'Unknown' }}</x-text>
                            <x-text color="{{ $roleColor }}">{{ $log->user?->username ?? '-' }}</x-text>
                        </x-table-cell>
                        <x-table-cell class="px-4 py-3">
                            <x-badge color="{{ $badge['color'] }}">
                                {{ $badge['label'] }}
                            </x-badge>
                        </x-table-cell>
                        <x-table-cell class="px-4 py-3">
                            <x-text>{{ $log->created_at->format('M j, Y') }}</x-text>
                            <x-text>{{ $log->created_at->format('g:i A') }}</x-text>
                        </x-table-cell>
                        <x-table-cell class="px-4 py-3 text-zinc-600 dark:text-zinc-400 truncate">
                            {{ $log->subject }}
                        </x-table-cell>
                        <x-table-cell class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $log->channel }}
                        </x-table-cell>
                        <x-table-cell class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <flux:button 
                                    wire:click="getLogDetail({{ $log->id }})" 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="eye" 
                                    aria-label="View log" 
                                />
                                <flux:button 
                                    wire:click="confirmDeleteLog({{ $log->id }})" 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="trash" 
                                    aria-label="Delete log" />
                            </div>
                        </x-table-cell>
                    </x-table-row>
                @empty
                    <x-table-row>
                        <x-table-cell colspan="6" class="px-4 py-10 text-center text-sm text-zinc-400">
                            No audit logs match your current filters.
                        </x-table-cell>
                    </x-table-row>
                @endforelse
            </x-table-rows>
        </x-table>
    </div>

    <div class="mt-4">
        {{ $this->getAuditLogs->links() }}
    </div>

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
            :key="'delete-' .$selectedDeletingLog->id" 
        />
        @endif
    </flux:modal>
</div>