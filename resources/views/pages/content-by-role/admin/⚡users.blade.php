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



new  #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public $filtered_role = "";
    public $search = "";
    public $selectedUserId = null;

    public $user;

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

    #[Computed]
    public function getUsers() {
        return User::with('card', 'userStatus')
            ->whereIn('role', ['operator', 'commuter'])
            ->when($this->filtered_role, fn($q) => $q->where('role', $this->filtered_role))
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
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <x-pages-heading heading="Users" description="Every commuter and operator registered in the system." />

            <div class="flex items-center gap-2 shrink-0">
                <flux:button variant="outline" icon="arrow-down-tray" size="sm" class="font-secondary">
                    Export
                </flux:button>
                <flux:link href="{{ route('admin.register.user') }}" wire:navigate>
                    <flux:button variant="primary" icon="plus" size="sm" class="font-secondary">
                        Add user
                    </flux:button>
                </flux:link>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mb-5">
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
            <div class="inline-flex items-center gap-1.5 p-1.5 rounded-lg bg-light-subtle dark:bg-dark-subtle w-fit">
                @foreach ([
                    ''          => 'All',
                    'commuter'  => 'Commuters',
                    'operator'  => 'Operators',
                ] as $value => $label)
                    <button
                        type="button"
                        wire:click="setRoleFilter('{{ $value }}')"
                        class="px-4 py-2 rounded-md font-secondary text-sm sm:text-table-row font-medium transition-colors cursor-pointer
                            {{ $filtered_role === $value
                                ? 'bg-light-secondary dark:bg-dark-secondary text-light-txt-primary dark:text-dark-txt-primary shadow-sm'
                                : 'text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-body' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

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

        <flux:card class="mb-4 p-0! overflow-hidden">
            <div class="overflow-x-auto">
                <flux:table container:class="max-h-160">
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
                                                @if ($user->isSuspended())
                                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Suspended</flux:badge>
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