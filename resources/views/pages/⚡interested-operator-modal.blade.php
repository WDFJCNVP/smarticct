<?php

use Livewire\Component;
use Livewire\Attributes\Validate;

use App\Models\PostInterest;

new class extends Component
{
    public $selected_post;
    public $message;

    public function sendRequest() {
        PostInterest::create([
            'post_id' => $this->selected_post->id,
            'user_id' => auth()->id(),
            'purpose' => $this->message,
        ]);
    }
};
?>

<div>
    <div class="flex items-center gap-1 mb-4">
        <flux:avatar size="sm" name="{{ $this->selected_post->user->name }}" />
        <div>
            <x-text size="lg" variant="strong">{{ $selected_post->user->name }}</x-text>
        </div>
    </div>
    <flux:textarea label="Message to client" placeholder="Enter your message to the client" wire:model="message"/>
    <div class="mt-2">
        <x-button wire:click="sendRequest" variant="primary">Send</x-button>
    </div>
</div>