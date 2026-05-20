<?php

use Livewire\Component;
use App\Models\User;

new class extends Component
{
    public $totalOperator;

    public function mount() {
        $this->countTotalOperator();
    }

    public function countTotalOperator() {
        
        $this->totalOperator = User::where('role','operator')->count();

    }
};
?>

<div wire:poll.visible.30s="countTotalOperator">
    {{ $totalOperator }}
</div>