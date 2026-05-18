<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Card;

new #[Layout('components.dashboard.operator-dashboard')] class extends Component
{
    public function getBalanceProperty()
    {

        $user = auth()->user();

        return Card::where('user_id', $user->id)
            ->where('status', 'active')
            ->value('balance') ?? 0; 
    }

};
?>

<div>
    <flux:card wire:poll.5s>
        <flux:heading size="lg">{{ $this->getBalanceProperty() }}</flux:heading>

        <flux:text class="mt-2 mb-4">
            Total Points
        </flux:text>
    </flux:card>
</div>