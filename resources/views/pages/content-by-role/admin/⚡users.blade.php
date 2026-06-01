<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Events\UserInfoUpdated;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\User;


new class extends Component
{
    use WithPagination;

    public $filtered_role;
    public $search;
    public $selectedUserId = null;

    public $user;

    public function selectUser($id) {
        $this->selectedUserId = $id;
    }

    #[Computed]
    public function getUsers() {
        return User::with('card')
            ->whereIn('role', ['operator', 'passenger'])
            ->when(
                $this->filtered_role,
                fn($q) => $q->where('role', $this->filtered_role)
            )
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('username', 'like', '%' . $this->search . '%')
                        ->orWhere('id', 'like', '%' . $this->search . '%');
                })
            )->paginate(10);
    }

    #[On('echo:user-info-updated,UserInfoUpdated')]
    public function refreshUserInfo() {

        unset($this->getUsers);
    }



};
?>

<div class="mt-10">

    <div class="flex items-center gap-3">
        <div class="flex-1 w-full">
            <flux:heading size="lg" class="flex gap-2">
                All Users
                <flux:text size="lg" variant="subtle">{{ $this->getUsers->total() }}</flux:text>
            </flux:heading>
        </div>
        <div class="flex items-center gap-2">
            <flux:input class="max-w-xs" size="sm" icon="magnifying-glass" placeholder="Search" wire:model.live.blur.300ms="search" />

            <flux:select wire:model.live="filtered_role" size="sm" class="w-36">
                <flux:select.option value="">All roles</flux:select.option>
                <flux:select.option value="operator">Operator</flux:select.option>
                <flux:select.option value="passenger">Commuter</flux:select.option>
            </flux:select>

            <flux:link href="{{ route('admin.register.user') }}" wire:navigate>
                <flux:button variant="primary" color="zinc" icon="plus" size="sm">Add Users</flux:button>
            </flux:link>
        </div>
    </div>

    <flux:table container:class="max-h-80 mt-5">
        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
            <flux:table.column>ID</flux:table.column>
            <flux:table.column>Card No.</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Address</flux:table.column>
            <flux:table.column>Role</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->getUsers as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->id }}</flux:table.cell>
                    <flux:table.cell>{{ $user->card->uid }}</flux:table.cell>
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->username }}</flux:table.cell>
                    <flux:table.cell>{{ $user->address }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->role === 'operator')
                            <flux:badge color="blue" size="sm" inset="top bottom">operator</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm" inset="top bottom">commuter</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        
                    <flux:link href="/admin/edit/user/{{ $user->id }}" wire:navigate>
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="ellipsis-horizontal"
                            inset="top bottom"
                            {{-- wire:click="selectUser({{ $user->id }})"
                            x-on:click="$flux.modal('edit-user').show()" --}}
                        />
                    </flux:link>

                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->getUsers->links() }}
    </div>

    <flux:modal name="edit-user" class="w-full space-y-6" style="max-width: 898px;">
        @if ($selectedUserId)
            <livewire:pages::content-by-role.admin.edit_user
                :user_id="$selectedUserId"
                :key="$selectedUserId"
            />
        @endif
    </flux:modal>

</div>