<?php

use Livewire\Component;
use Illuminate\Http\Request;
use App\Models\User;

new class extends Component
{
    public User $user;

    public $name;
    public $email;
    public $address;

    public function mount($user_id) {
        
        $this->user = User::findOrFail($user_id);

        $this->name    = $this->user->name;
        $this->email   = $this->user->email;
        $this->address = $this->user->address;
    }

    public function save() {

        $validated = $this->validate([
            'name' => ['required'],
            'email' => ['required', 'email'],
            'address' => ['required'],
        ]);

        $this->user->update($validated);
    }

    public function deleteUser() {

        $this->user->delete();

        $this->redirect('/admin/users');
    }

};
?>

<div>
    <form wire:submit="save">
        @csrf

        <div class="grid w-full grid-cols-2 gap-6">
            <flux:input label="Name" name="name" wire:model="name" class="w-full" />
            <flux:input label="Email" name="email" wire:model="email" class="w-full" />
        </div>

        <flux:input label="Address" name="address" wire:model="address" class="w-full" />

        <div class="flex w-full gap-2 mt-8">
            <div class="flex flex-1">
                
                <flux:modal.trigger name="delete-profile">
                    <flux:button variant="danger">Delete</flux:button>
                </flux:modal.trigger>

            </div>
            <div class="flex gap-2">
                <flux:button variant="primary" type="submit">Save Changes</flux:button>
            </div>
        </div>
    </form>

    <flux:modal name="delete-profile" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete user?</flux:heading>
                <flux:text class="mt-2">
                    You're about to delete <strong>{{$this->user->name}}</strong>.<br>
                    This action cannot be reversed.
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button x-on:click="$flux.modal('delete-profile').close()">Close</flux:button>
                <flux:button variant="danger" wire:click="deleteUser">
                    Delete user
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>