<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;

new class extends Component
{
    use WithPagination;

    public $filtered_role;
    public $search;

    public array $user_name    = [];
    public array $user_email   = [];
    public array $user_address = [];

    public function getUsers()
    {
        $users = User::with('card')
            ->whereIn('role', ['operator', 'passenger'])
            ->when(
                $this->filtered_role,
                fn($q) => $q->where('role', $this->filtered_role)
            )
            ->when(
                $this->search,
                fn($q) => $q->where(function ($q2) {
                    $q2->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('id', 'like', '%' . $this->search . '%');
                })
            )->paginate(10);

        foreach ($users as $user) {
            $this->user_name[$user->id]    ??= $user->name;
            $this->user_email[$user->id]   ??= $user->email;
            $this->user_address[$user->id] ??= $user->address;
        }

        return $users;
    }

    public function saveUser(int $id): void
    {
        $user = User::findOrFail($id);

        $user->update([
            'name'    => $this->user_name[$id]    ?? $user->name,
            'email'   => $this->user_email[$id]   ?? $user->email,
            'address' => $this->user_address[$id] ?? $user->address,
        ]);

        $this->dispatch('close-modal', name: "edit-user-{$id}");
    }

    public function deleteUser(int $id): void
    {
        User::destroy($id);

        $this->dispatch('close-modal', name: "edit-user-{$id}");
        $this->dispatch('close-modal', name: "delete-profile-{$id}");
    }
};
?>

<div class="mt-10">

    <div class="flex items-center gap-3">
        <flux:input class="max-w-xs" size="sm" placeholder="Search..." wire:model.live="search" />

        <flux:select wire:model.live="filtered_role" size="sm" class="w-36">
            <flux:select.option value="">All roles</flux:select.option>
            <flux:select.option>Operator</flux:select.option>
            <flux:select.option value="passenger">Commuter</flux:select.option>
        </flux:select>
    </div>

    <flux:table container:class="max-h-80 mt-5" :paginate="$this->getUsers()" wire:poll.visible.30s="getUsers">
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
            @foreach ($this->getUsers() as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->id }}</flux:table.cell>
                    <flux:table.cell>{{ $user->card->uid }}</flux:table.cell>
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>{{ $user->address }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->role === 'operator')
                            <flux:badge color="blue" size="sm" inset="top bottom">operator</flux:badge>
                        @else
                            <flux:badge color="yellow" size="sm" inset="top bottom">commuter</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        
                        <flux:modal.trigger name="edit-user-{{ $user->id }}">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                        </flux:modal.trigger>

                        <flux:modal name="edit-user-{{ $user->id }}" class="w-full space-y-6" style="max-width: 672px;">
                            <div>
                                <flux:heading size="lg">User Information</flux:heading>
                                <flux:subheading>Viewing details for {{ $user->name }}</flux:subheading>
                            </div>

                            <div class="space-y-4 w-full border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 bg-zinc-50 dark:bg-zinc-800/50">
                                <div class="flex items-center gap-4">
                                    <flux:avatar src="{{ $user->avatar_url }}" name="{{ $user->name }}" size="xl" />
                                    <div>
                                        <div class="font-semibold text-base text-zinc-800 dark:text-zinc-200">{{ $user->name }}</div>
                                        <div class="text-sm text-zinc-500">{{ $user->email }}</div>
                                    </div>
                                </div>

                                <flux:separator variant="dashed" />

                                <div class="grid w-full grid-cols-2 gap-6 text-sm">
                                    <div>
                                        <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Role</span>
                                        <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $user->role ?? 'User' }}</span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-zinc-400 font-medium uppercase tracking-wider">Joined</span>
                                        <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $user->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>

                                {{-- form --}}

                                <livewire:pages::content-by-role.admin.edit_user :user_id="$user->id" />

                            </div>
                        </flux:modal>

                    </flux:table.cell>
                </flux:table.row>
            @endforeach

        </flux:table.rows>
    </flux:table>
</div>