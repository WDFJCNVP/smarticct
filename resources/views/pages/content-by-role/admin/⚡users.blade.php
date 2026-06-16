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
        // app(QueueManagementService::class)->generateSchedule(today());
    }

    public function selectUser($id) {
        $this->selectedUserId = $id;
    }

    #[Computed]
    public function getUsers() {
        return User::with('card')
            ->whereHas('card')
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

    <div class="grid grid-cols-4 gap-3 mt-6 mb-5">
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Total users</p>
            <p class="text-2xl font-medium text-zinc-800 dark:text-zinc-100">
                {{ $this->getUsers->total() }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Operators</p>
            <p class="text-2xl font-medium text-blue-700 dark:text-blue-400">
                {{ User::where('role', 'operator')->count() }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Commuters</p>
            <p class="text-2xl font-medium text-amber-700 dark:text-amber-400">
                {{ User::where('role', 'commuter')->count() }}
            </p>
        </div>
        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-4">
            <p class="text-xs text-zinc-400 mb-1">Registered today</p>
            <p class="text-2xl font-medium text-purple-700 dark:text-purple-400">
                {{ User::whereIn('role', ['operator', 'commuter'])->whereDate('created_at', today())->count() }}
            </p>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 mb-4">
        <div class="flex items-center gap-2">
            <flux:input
                class="max-w-xs"
                size="sm"
                icon="magnifying-glass"
                placeholder="Search name, ID, username…"
                wire:model.live.debounce.300ms="search"
            />
            <flux:select wire:model.live="filtered_role" size="sm" class="w-36">
                <flux:select.option value="">All roles</flux:select.option>
                <flux:select.option value="operator">Operator</flux:select.option>
                <flux:select.option value="commuter">Commuter</flux:select.option>
            </flux:select>
            <flux:link href="{{ route('admin.register.user') }}" wire:navigate>
                <flux:button variant="primary" icon="plus" size="sm">Add user</flux:button>
            </flux:link>
        </div>
    </div>

    <flux:table container:class="max-h-160">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>ID</flux:table.column>
            <flux:table.column>Card no.</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Username</flux:table.column>
            <flux:table.column>Address</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->getUsers as $user)
                <flux:table.row :key="$user->id">

                    <flux:table.cell class="text-zinc-400 text-xs">
                        {{ $user->user_code }}
                    </flux:table.cell>

                    <flux:table.cell class="font-mono text-xs text-zinc-500">
                        **** **** **** {{ substr($user->card->card_number, -4) }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:avatar size="xs"
                                src="{{ $user->avatar_url }}"
                                name="{{ $user->name }}"
                            />
                            {{ $user->name }}
                        </div>
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500 text-xs">
                        {{ $user->username }}
                    </flux:table.cell>

                    <flux:table.cell class="text-zinc-500 text-xs max-w-48 truncate">
                        {{ $user->address }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($user->role === 'operator')
                            <flux:badge color="blue" size="sm">Operator</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm">Commuter</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:link href="/admin/edit/user/{{ $user->id }}" wire:navigate>
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="ellipsis-horizontal"
                                inset="top bottom"
                            />
                        </flux:link>
                    </flux:table.cell>

                </flux:table.row>

            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7">
                        <div class="flex flex-col items-center justify-center py-12 gap-2">
                            <flux:icon.users class="w-8 h-8 text-zinc-300" />
                            <p class="text-sm text-zinc-400">No users found.</p>
                            @if ($search)
                                <p class="text-xs text-zinc-400">Try a different search term.</p>
                            @elseif ($filtered_role)
                                <p class="text-xs text-zinc-400">No {{ $filtered_role }}s registered yet.</p>
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->getUsers->links() }}
    </div>
</div>