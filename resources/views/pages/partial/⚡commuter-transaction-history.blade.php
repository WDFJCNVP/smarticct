<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;

use App\Models\RentTransaction;

new class extends Component
{
    use WithPagination;

    #[Computed]
    #[On('transaction-updated')]
    public function getCompletedTransactions()
    {
        return RentTransaction::with([
                'tripRequest.user', 'tripRequest.post.user',
                'rentalOffer.user', 'rentalOffer.post.user'
            ])
            ->whereIn('status', ['completed', 'cancelled'])
            ->where(function ($query) {
                // Fetch if the current user is the post owner OR the interested user
                $query->where('post_owner_id', auth()->id())
                      ->orWhere('interested_user_id', auth()->id());
            })
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <flux:card class="overflow-hidden p-0">
        <flux:table>
            <flux:table.columns sticky class="bg-light-secondary dark:bg-dark-secondary">
                <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Operator</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Date Accepted</flux:table.column>
                <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->getCompletedTransactions as $record)
                    @php
                        $operatorName = 'Unknown Operator';

                        // Extract strictly the Operator's name based on the table origin
                        if ($record->tripRequest) {
                            // For trip requests, the Operator is the one who posted the vehicle
                            $operatorName = $record->tripRequest->post->user->name;
                        } elseif ($record->rentalOffer) {
                            // For rental offers, the Operator is the one who offered their vehicle
                            $operatorName = $record->rentalOffer->user->name;
                        }
                    @endphp

                    <flux:table.row :key="$record->id">
                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">
                            {{ $operatorName }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                            {{ $record->created_at->format('D, M j, Y') }}
                        </flux:table.cell>

                        <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                            @if($record->status === 'completed')
                                <flux:badge size="sm" color="green" icon="check">Completed</flux:badge>
                            @elseif($record->status === 'cancelled')
                                <flux:badge size="sm" color="amber" icon="x-mark">Cancelled</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center py-12">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <flux:icon.document-text class="w-8 h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No completed transactions yet.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($this->getCompletedTransactions->hasPages())
        <div class="mt-4">
            {{ $this->getCompletedTransactions->links() }}
        </div>
    @endif
</div>
