<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Models\RentTransaction;
use App\Models\RentalOffer;
use App\Models\User;

new class extends Component
{
    public $post;

    public $isMarkAsCompleteModalOpen = false;
    public $isMarkAsCancelModalOpen = false;

    public function markAsCancelModal() {
        $this->isMarkAsCancelModalOpen = true;
    }

    public function markAsCompleteModal() {
        $this->isMarkAsCompleteModalOpen = true;
    }

    public function markAsCancel() {

        // Guard: prevents "Call to a member function update() on null"
        // if this is triggered twice (e.g. modal not yet closed).
        if (! $this->getActiveTransactionRecord) {
            $this->isMarkAsCancelModalOpen = false;
            return;
        }

        $getActiveTransactionRecord = $this->getActiveTransactionRecord;
        $post = $this->post;

        $rentalOffer = DB::transaction(function () use ($getActiveTransactionRecord, $post){

            $getActiveTransactionRecord->update(['status' => 'cancelled']);
            $rentalOffer = RentalOffer::where('id', $getActiveTransactionRecord->rental_offer_id)->update(['status' => 'cancel']);
            $post->update(['status' => 'published']);

            return $rentalOffer;
        });

        unset($this->getActiveTransactionRecord);
        unset($this->interestedUser);

        $this->isMarkAsCancelModalOpen = false;

        $this->dispatch('transaction-updated');
        
        if ($rentalOffer) {

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Marked as cancelled',
                text: 'Transaction has been marked as cancelled.',
            );

        }
    }

    public function markAsComplete() {

        // Guard: prevents "Call to a member function update() on null"
        // if this is triggered twice (e.g. modal not yet closed).
        if (! $this->getActiveTransactionRecord) {
            $this->isMarkAsCompleteModalOpen = false;
            return;
        }

        $activeTransactionRecord = $this->getActiveTransactionRecord;
        $post = $this->post;

        $activeTransactionRecord->update(['status' => 'completed']);

        $rentalOffer = RentalOffer::where('id', $activeTransactionRecord->rental_offer_id)->update(['status' => 'completed']);

        $post->update([
            'status' => 'archived',
            'metadata' => array_merge($post->metadata ?? [], [
                'transaction_completed'  => true,
                'completed_at'           => now()->toDateTimeString(),
                'completed_with_user_id' => $activeTransactionRecord->interested_user_id,
            ]),
        ]);

        unset($this->getActiveTransactionRecord);
        unset($this->interestedUser);

        $this->isMarkAsCompleteModalOpen = false;

        $this->dispatch('transaction-updated'); 

        if ($rentalOffer) {

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Marked as complete',
                text: 'Transaction has been marked as complete.',
            );

        }
    }   

    #[Computed]
    public function getActiveTransactionRecord() {
        return RentTransaction::with('rentalOffer.vehicle')
            ->where('post_owner_id', $this->post->user_id)
            ->where('status', 'ongoing')
            ->whereHas('rentalOffer', function ($query) {
                $query->where('post_id', $this->post->id);
            })
            ->first();
    }

    #[Computed]
    public function interestedUser() {
        if (!$this->getActiveTransactionRecord) return null;
        return User::find($this->getActiveTransactionRecord->interested_user_id);
    }

    #[On('transaction-updated')]
    public function refreshActiveTransaction() {
        unset($this->getActiveTransactionRecord);
        unset($this->interestedUser);
    }
};
?>

<div>
    @if ($this->getActiveTransactionRecord)
        <x-card class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <x-avatar name="{{ $this->interestedUser->name }}" />
                    <div>
                        <x-text variant="subtle" class="block" style="font-size: var(--text-timestamp)">Client's name</x-text>
                        <x-text variant="strong" class="block text-lg text-light-txt-primary dark:text-dark-txt-primary">{{ $this->interestedUser->name }}</x-text>
                        <x-text variant="strong" class="block text-light-txt-primary dark:text-dark-txt-primary" style="font-size: var(--text-table-row)">{{ $this->interestedUser->phone_number }}</x-text>
                    </div>
                </div>
                <flux:badge color="orange">{{ ucfirst($this->getActiveTransactionRecord->status) }}</flux:badge>
            </div>

            <div class="mt-4 rounded-lg border border-light-bd-default dark:border-dark-bd-default divide-y divide-light-bd-default dark:divide-dark-bd-default">
                <div class="flex items-center justify-between gap-3 p-3">
                    <x-text variant="subtle" style="font-size: var(--text-table-row)">Available date</x-text>
                    <x-text variant="strong" class="text-right" style="font-size: var(--text-table-row)">
                        {{ $this->getActiveTransactionRecord->rentalOffer->available_from?->format('D, M j Y') }}
                        &ndash;
                        {{ $this->getActiveTransactionRecord->rentalOffer->available_until?->format('D, M j Y') }}
                    </x-text>
                </div>

                <div class="flex items-center justify-between gap-3 p-3">
                    <x-text variant="subtle" style="font-size: var(--text-table-row)">Vehicle type</x-text>
                    <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->getActiveTransactionRecord->rentalOffer->vehicle->vehicle_type ?? 'N/A' }}</x-text>
                </div>

                <div class="flex items-center justify-between gap-3 p-3">
                    <x-text variant="subtle" style="font-size: var(--text-table-row)">Plate number</x-text>
                    <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->getActiveTransactionRecord->rentalOffer->vehicle->plate_number ?? 'N/A' }}</x-text>
                </div>

                <div class="flex items-center justify-between gap-3 p-3">
                    <x-text variant="subtle" style="font-size: var(--text-table-row)">Seat capacity</x-text>
                    <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->getActiveTransactionRecord->rentalOffer->vehicle->total_seats ?? 'N/A' }}</x-text>
                </div>

                <div class="flex items-center justify-between gap-3 p-3">
                    <x-text variant="subtle" style="font-size: var(--text-table-row)">Destination</x-text>
                    <x-text variant="strong" class="text-right" style="font-size: var(--text-table-row)">{{ $this->getActiveTransactionRecord->rentalOffer->destination_coverage }}</x-text>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-light-bd-default dark:border-dark-bd-default flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <x-button wire:click="markAsCompleteModal" variant="primary" color="green" class="w-full sm:w-auto">Mark as Completed</x-button>
                <x-button wire:click="markAsCancelModal" variant="primary" color="red" class="w-full sm:w-auto">Cancel transaction</x-button>
            </div>
        </x-card>
    @else
        <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
            <flux:icon name="clipboard-document-list" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                No active transaction.
            </x-text>
        </x-card>
    @endif

    <!-- ==================== -->
    <!-- MARK AS COMPLETE MODAL (feed style) -->
    <!-- ==================== -->
    <flux:modal
        wire:model="isMarkAsCompleteModalOpen"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Complete this transaction?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This action cannot be undone.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <x-button type="button" variant="ghost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </x-button>
                </flux:modal.close>
                <x-button
                    wire:click="markAsComplete"
                    wire:loading.attr="disabled"
                    type="button"
                    variant="primary"
                    color="green"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Complete
                </x-button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        wire:model="isMarkAsCancelModalOpen"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Cancel this transaction?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This action cannot be undone.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Footer -->
            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <x-button type="button" variant="ghost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </x-button>
                </flux:modal.close>
                <x-button
                    wire:click="markAsCancel"
                    wire:loading.attr="disabled"
                    type="button"
                    variant="primary"
                    color="red"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Cancel transaction
                </x-button>
            </div>
        </div>
    </flux:modal>
</div>