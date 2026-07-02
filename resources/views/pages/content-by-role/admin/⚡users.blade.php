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

    public $filtered_role;
    public $search;
    public $selectedUserId = null;

    public $user;

    public function mount() {
        app(QueueManagementService::class)->generateSchedule(today());
    }

    public function selectUser($id) {
        $this->selectedUserId = $id;
    }

    #[Computed]
    public function getUsers() {
        return User::with('card')
            ->whereIn('role', ['operator', 'commuter'])
            ->when($this->filtered_role, fn($q) => $q->where('role', $this->filtered_role))
            ->when($this->search, fn($q) => $q->where(function ($q2) {
                $q2->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('username', 'like', '%' . $this->search . '%')
                ->orWhere('user_code', 'like', '%' . $this->search . '%');
            }))
            ->paginate(10);
    }



    #[On('echo:user-info-updated,.UserInfoUpdated')]
    public function refreshUserInfo() {

        unset($this->getUsers);
    }



};
?>

<div>
    <x-pages-heading heading="Users" description="View all registered users in the system." />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mt-6 mb-5">
        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20">
                    <flux:icon.users class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total users
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary">
                {{ $this->getUsers->total() }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-info/10 dark:bg-dark-info/20">
                    <flux:icon.truck class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-info dark:text-dark-info" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Operators
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-info dark:text-dark-info">
                {{ User::where('role', 'operator')->count() }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-warning/10 dark:bg-dark-warning/20">
                    <flux:icon.user class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-warning dark:text-dark-warning" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Commuters
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-warning dark:text-dark-warning">
                {{ User::where('role', 'commuter')->count() }}
            </x-text>
        </flux:card>

        <flux:card class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-start sm:justify-start p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20">
                    <flux:icon.calendar-days class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Registered today
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success">
                {{ User::whereIn('role', ['operator', 'commuter'])->whereDate('created_at', today())->count() }}
            </x-text>
        </flux:card>
    </div>

    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-3 mb-4">
        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:input
                class="w-full sm:w-64 font-secondary text-table-row"
                size="sm"
                icon="magnifying-glass"
                placeholder="Search name, ID, username…"
                wire:model.live.debounce.300ms="search"
            />
            <flux:select wire:model.live="filtered_role" size="sm" class="w-full sm:w-36 font-secondary text-table-row">
                <flux:select.option value="">All roles</flux:select.option>
                <flux:select.option value="operator">Operator</flux:select.option>
                <flux:select.option value="commuter">Commuter</flux:select.option>
            </flux:select>
            <flux:link href="{{ route('admin.register.user') }}" wire:navigate class="w-full sm:w-auto">
                <flux:button variant="primary" icon="plus" size="sm" class="font-secondary w-full sm:w-auto justify-center">
                    Add user
                </flux:button>
            </flux:link>
        </div>
    </div>

    <div class="rounded-xl border border-light-bd-default dark:border-dark-bd-default overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-secondary items-center bg-light-subtle/50 dark:bg-dark-secondary font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">ID</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Card</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Name</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Username</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Address</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Role</flux:table.column>
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getUsers as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->user_code }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->card ? '**** **** **** ' . substr($user->card->card_number, -4) : '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center gap-1.5 md:gap-2">
                                    <flux:avatar size="xs" src="{{ $user->avatar_url }}" name="{{ $user->name }}" />
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                        {{ $user->name }}
                                    </span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->username }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted max-w-48 truncate">
                                {{ $user->address ?: '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($user->role === 'operator')
                                    <flux:badge color="blue" size="sm" class="font-secondary text-badge text-xs">Operator</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Commuter</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2">
                                <flux:link href="/admin/edit/user/{{ $user->id }}" wire:navigate>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" class="scale-75 md:scale-100" />
                                </flux:link>
                            </flux:table.cell>
                        </flux:table.row>

                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.users class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No users found.
                                    </x-text>
                                    @if ($search)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            Try a different search term.
                                        </x-text>
                                    @elseif ($filtered_role)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            No {{ $filtered_role }}s registered yet.
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
    </div>
</div>