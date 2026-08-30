<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Events\UserInfoUpdated;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\User;
use App\Services\QueueManagementService;
use Illuminate\Support\Carbon;

use App\Jobs\ProcessAfterDepart;
use App\Models\Queue;
use App\Models\Vehicle;



new  #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public $filtered_role = "";
    public $filtered_status = "";
    public $search = "";
    public $selectedUserId = null;

    public $user;

    // ===================== EXPORT OPERATORS MODAL =====================

    public string $exportDateFrom = '';
    public string $exportDateTo = '';
    public string $exportVehicleType = '';
    public string $exportStatus = '';

    // Left blank by default — an export scoped to "today" would exclude
    // almost every existing operator, so this starts as "all time" and can
    // be narrowed down from there.
    public function prepareExportModal()
    {
        $this->exportDateFrom = '';
        $this->exportDateTo = '';
        $this->exportVehicleType = '';
        $this->exportStatus = '';
    }

    public function resetExportDateRange()
    {
        $this->exportDateFrom = '';
        $this->exportDateTo = '';
    }

    public function setExportDateRangeToday()
    {
        $this->exportDateFrom = today()->toDateString();
        $this->exportDateTo = today()->toDateString();
    }

    #[Computed]
    public function exportUrl(): string
    {
        return route('admin.operators.export', array_filter([
            'from'         => $this->exportDateFrom,
            'to'           => $this->exportDateTo,
            'vehicle_type' => $this->exportVehicleType,
            'status'       => $this->exportStatus,
        ]));
    }

    #[Computed]
    public function vehicleTypes()
    {
        return Vehicle::query()->distinct()->pluck('vehicle_type');
    }

    public function mount() {

        app(QueueManagementService::class)->generateSchedule(today());
    }

    public function selectUser($id) {
        $this->selectedUserId = $id;
    }

    public function setRoleFilter($role) {
        $this->filtered_role = $role;
        $this->resetPage();
    }

    public function updatedFilteredStatus() {
        $this->resetPage();
    }

    // Worst-case OR/CR + franchise status across an operator's vehicles,
    // for the "Docs Expiring" / "Docs Expired" badge in the users table.
    public function operatorDocumentStatus(User $user): ?string
    {
        if ($user->role !== 'operator') {
            return null;
        }

        $statuses = $user->vehicles->map(fn ($vehicle) => $vehicle->documentStatus())->filter();

        if ($statuses->contains('expired')) {
            return 'expired';
        }

        if ($statuses->contains('expiring')) {
            return 'expiring';
        }

        return null;
    }

    #[Computed]
    public function getUsers() {
        return User::with('card', 'userStatus', 'vehicles')
            ->whereIn('role', ['operator', 'commuter'])
            ->when($this->filtered_role, fn($q) => $q->where('role', $this->filtered_role))
            ->when($this->filtered_status, function ($q) {
                match ($this->filtered_status) {
                    'active' => $q->whereDoesntHave('userStatus', function ($s) {
                        $s->where('is_deleted', true)->orWhere('status', 'suspended');
                    }),
                    'suspended' => $q->whereHas('userStatus', function ($s) {
                        $s->where('status', 'suspended')->where('is_deleted', false);
                    }),
                    'deleted' => $q->whereHas('userStatus', function ($s) {
                        $s->where('is_deleted', true);
                    }),
                    default => null,
                };
            })
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('email_address', 'like', '%' . $this->search . '%')
                ->orWhere('user_code', 'like', '%' . $this->search . '%');
            }))
            ->paginate(10);
    }

    #[Computed]
    public function withCardCount() {
        return User::whereIn('role', ['operator', 'commuter'])
            ->whereHas('card')
            ->count();
    }



    #[On('echo:user-info-updated,.UserInfoUpdated')]
    public function refreshUserInfo() {

        unset($this->getUsers);
        unset($this->withCardCount);
    }



};
?>

<div>
    {{-- Header – updated to match standard format --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Users
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Every commuter and operator registered in the system.
            </x-text>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto shrink-0">
            <flux:modal.trigger name="export-operators" wire:click="prepareExportModal">
                <flux:button
                    variant="outline"
                    icon="arrow-down-tray"
                    size="sm"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Export Operators
                </flux:button>
            </flux:modal.trigger>
            <flux:link href="{{ route('admin.register.user') }}" wire:navigate class="w-full sm:w-auto">
                <flux:button variant="primary" icon="plus" size="sm" class="font-secondary w-full sm:w-auto justify-center">
                    Add user
                </flux:button>
            </flux:link>
        </div>
    </div>

    {{-- ===================== EXPORT OPERATORS MODAL ===================== --}}
    <flux:modal
        name="export-operators"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export operators
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Choose what to include in the spreadsheet. Leave the date range blank to include every registered vehicle.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Registered date range</flux:label>
                <div class="flex items-center gap-2">
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
                <div class="flex items-center gap-2 mt-2">
                    <button
                        type="button"
                        wire:click="resetExportDateRange"
                        @class([
                            'px-3 py-1 rounded-md font-secondary text-xs font-medium transition-colors',
                            'bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary' => $exportDateFrom === '' && $exportDateTo === '',
                            'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary' => !($exportDateFrom === '' && $exportDateTo === ''),
                        ])
                    >
                        All time
                    </button>
                    <button
                        type="button"
                        wire:click="setExportDateRangeToday"
                        @class([
                            'px-3 py-1 rounded-md font-secondary text-xs font-medium transition-colors',
                            'bg-light-subtle dark:bg-dark-subtle text-light-txt-primary dark:text-dark-txt-primary' => $exportDateFrom === today()->toDateString() && $exportDateTo === today()->toDateString(),
                            'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary' => !($exportDateFrom === today()->toDateString() && $exportDateTo === today()->toDateString()),
                        ])
                    >
                        Today
                    </button>
                </div>
            </flux:field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Vehicle type</flux:label>
                    <flux:select
                        wire:model.live="exportVehicleType"
                        size="sm"
                        placeholder="All vehicle types"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    >
                        <flux:select.option value="">All vehicle types</flux:select.option>
                        @foreach ($this->vehicleTypes as $type)
                            <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Account status</flux:label>
                    <flux:select
                        wire:model.live="exportStatus"
                        size="sm"
                        placeholder="All statuses"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                    >
                        <flux:select.option value="">All statuses</flux:select.option>
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="suspended">Suspended</flux:select.option>
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
                    href="{{ $this->exportUrl }}"
                    icon="arrow-down-tray"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Download Excel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 mb-5">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.users class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total users
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->getUsers->total() }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                across all roles
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20 shrink-0">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Operators
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info block">
                {{ User::where('role', 'operator')->count() }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                vehicle owners &amp; drivers
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    With card
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                {{ $this->withCardCount }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                {{ $this->getUsers->total() - $this->withCardCount }} awaiting issuance
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20 shrink-0">
                    <flux:icon.calendar-days class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Registered today
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning block">
                {{ User::whereIn('role', ['operator', 'commuter'])->whereDate('created_at', today())->count() }}
            </x-text>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">
                new sign-ups
            </x-text>
        </flux:card>
    </div>

    {{-- Filter pills + search --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div class="flex sm:inline-flex items-center gap-1.5 p-1.5 rounded-lg bg-light-subtle dark:bg-dark-subtle w-full sm:w-fit">
            @foreach ([
                ''          => 'All',
                'commuter'  => 'Commuters',
                'operator'  => 'Operators',
            ] as $value => $label)
                <button
                    type="button"
                    wire:click="setRoleFilter('{{ $value }}')"
                    class="flex-1 sm:flex-none px-5 sm:px-6 py-2 rounded-md font-secondary text-sm sm:text-table-row font-medium text-center transition-colors cursor-pointer
                        {{ $filtered_role === $value
                            ? 'bg-light-secondary dark:bg-dark-secondary text-light-txt-primary dark:text-dark-txt-primary shadow-sm'
                            : 'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-body' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-2 w-full sm:flex-1">
            <flux:select
                wire:model.live="filtered_status"
                placeholder="All statuses"
                class="w-full sm:w-44 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="suspended">Suspended</flux:select.option>
                <flux:select.option value="deleted">Deleted</flux:select.option>
            </flux:select>

            <div class="w-full sm:flex-1 sm:max-w-md">
                <flux:input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, ID or email"
                    class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                    icon="magnifying-glass"
                />
            </div>
        </div>
    </div>

    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-3! md:px-4! py-2">User</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Role</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">ICCT Card</flux:table.column>
                    <flux:table.column align="center" class="hidden lg:table-cell px-2 md:px-4 py-2">Address</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-2 md:px-4 py-2">Joined</flux:table.column>
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getUsers as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell class="px-3! md:px-4! py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <flux:avatar size="sm" src="{{ $user->avatar_url }}" name="{{ $user->name }}" />
                                    <div class="min-w-0 flex flex-col">
                                        <span class="font-secondary text-xs md:text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary truncate flex items-center gap-1.5">
                                            {{ $user->name }}
                                            @if ($user->isDeleted())
                                                <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs">Deleted</flux:badge>
                                            @elseif ($user->isSuspended())
                                                <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Suspended</flux:badge>
                                            @endif
                                            @php($docStatus = $this->operatorDocumentStatus($user))
                                            @if ($docStatus === 'expired')
                                                <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Docs Expired</flux:badge>
                                            @elseif ($docStatus === 'expiring')
                                                <flux:badge color="orange" size="sm" class="font-secondary text-badge text-xs">Docs Expiring</flux:badge>
                                            @endif
                                        </span>
                                        <span class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted truncate">
                                            {{ $user->email_address }}
                                        </span>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-2.5">
                                @if ($user->role === 'operator')
                                    <flux:badge color="blue" size="sm" class="font-secondary text-badge text-xs">Operator</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Commuter</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-2.5">
                                @if ($user->card)
                                    <span class="font-mono text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        **** **** **** {{ substr($user->card->card_number, -4) }}
                                    </span>
                                @else
                                    <flux:badge color="zinc" size="sm" class="font-secondary text-badge text-xs">Not issued</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden lg:table-cell px-2 md:px-4 py-2.5 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted max-w-48 truncate">
                                {{ $user->address ?: '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-2 md:px-4 py-2.5 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->created_at->format('M j, Y') }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2! md:px-4! py-2.5">
                                <flux:link href="/admin/edit/user/{{ $user->id }}" wire:navigate>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" class="scale-75 md:scale-100" />
                                </flux:link>
                            </flux:table.cell>
                        </flux:table.row>

                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.users class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No users found.
                                    </x-text>
                                    @if ($this->search)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            Try a different search term.
                                        </x-text>
                                    @elseif ($this->filtered_status)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            No {{ $this->filtered_status }} users found.
                                        </x-text>
                                    @elseif ($this->filtered_role)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            No {{ $this->filtered_role }}s registered yet.
                                        </x-text>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getUsers->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getUsers->links() }}
            </div>
        @endif
    </flux:card>

{{-- <livewire:pages::content-by-role.admin.pending-users /> --}}
</div>