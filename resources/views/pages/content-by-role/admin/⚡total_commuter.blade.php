<?php

use Livewire\Component;
use App\Models\User;

new class extends Component
{
    public $totalPassenger;

    public function mount() {
        $this->countTotalPassenger();
    }

    public function countTotalPassenger() {
        
        $this->totalPassenger = User::where('role', 'passenger')->count();

    }
};
?>

<div wire:poll.visible.30s="countTotalPassenger">
    {{ $totalPassenger }}
</div>