<?php

use Livewire\Component;
use Livewire\WithPagination; 
use App\Models\User;

new class extends Component
{
    use WithPagination;

    public $filtered_role;
    public $search;

    public function getUsers() {
        return User::with('card')
                    ->whereIn('role', ['operator', 'passenger'])
                    ->when(
                        $this->filtered_role,
                        fn($q) => $q->where('role', $this->filtered_role)
                    )
                    ->when(
                        $this->search,
                        fn($q) => $q->where(function($q2) {
                            $q2->where('name', 'like', '%'. $this->search .'%')
                             ->orWhere('email', 'like', '%' . $this->search . '%')
                             ->orWhere('id', 'like', '%' . $this->search . '%');
                        })
                    )->paginate(10);
    }
};
?>

<div class="mt-10">

<div class="flex items-center gap-3">
    
    <flux:input class="max-w-xs" size="sm" placeholder="Search..." wire:model.live="search"/>

    <flux:select wire:model.live="filtered_role" size="sm" class="w-36">
        <flux:select.option value="">All roles</flux:select.option>
        <flux:select.option>Operator</flux:select.option>
        <flux:select.option value="passenger">Commuter</flux:select.option>
    </flux:select>
</div>

  <flux:table container:class="max-h-80 mt-5" :paginate="$this->getUsers()" pagination:scroll-to="#getUsers()">
    <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
        <flux:table.column >ID</flux:table.column>
        <flux:table.column sticky>Card No.</flux:table.column>
        <flux:table.column sticky>Name</flux:table.column>
        <flux:table.column sticky>Email</flux:table.column>
        <flux:table.column sticky>Address</flux:table.column>
        <flux:table.column sticky>Role</flux:table.column>
        <flux:table.column >Last login</flux:table.column>
    </flux:table.columns>

    <flux:table.rows>

      @foreach ($this->getUsers() as $user)

          <flux:table.row :key="$user->id">
            <flux:table.cell >{{$user->id}}</flux:table.cell>
            <flux:table.cell sticky>{{$user->card->uid}}</flux:table.cell>
            <flux:table.cell sticky>{{$user->name}}</flux:table.cell>
            <flux:table.cell sticky>{{$user->email}}</flux:table.cell>
            <flux:table.cell sticky>{{$user->address}}</flux:table.cell>
            <flux:table.cell>
                @if ($user->role === 'operator')

                    <flux:badge color="blue" size="sm" inset="top bottom">operator</flux:badge>

                @else

                    <flux:badge color="yellow" size="sm" inset="top bottom">Commuter</flux:badge>

                @endif
            </flux:table.cell>
            <flux:table.cell >Jul 29, 10:45 AM</flux:table.cell>
        </flux:table.row>

      @endforeach

    </flux:table.rows>
  </flux:table>
</div>