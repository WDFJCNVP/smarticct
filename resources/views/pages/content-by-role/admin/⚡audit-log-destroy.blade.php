<?php

use Livewire\Component;

new class extends Component
{
    public $selectedDeletingLog;

    public function destroyLog()
    {
        if ($this->selectedDeletingLog) {
            $this->selectedDeletingLog->delete();
            $this->selectedDeletingLog = null;
        }

        $this->redirect(route('admin.audit.logs'), navigate: true);
    }

};
?>

<div>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Delete this log?</flux:heading>
            <flux:text class="mt-2">
                You're about to delete this log.<br>
                This action cannot be reversed.
            </flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button wire:click="destroyLog" variant="danger">Delete log</flux:button>
        </div>
    </div>
</div>