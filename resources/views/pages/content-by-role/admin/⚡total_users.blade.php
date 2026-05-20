<?php

use Livewire\Component;
use App\Models\User;

new class extends Component
{
    public $totalUsers;

    public function mount() {
        $this->countTotalUsers();
    }

    public function countTotalUsers() {
        
        $this->totalUsers = User::whereIn('role', ['passenger', 'operator'])->count();

    }
};
?>

<div wire:poll.visible.30s="countTotalUsers">
    {{ $totalUsers }}
</div>