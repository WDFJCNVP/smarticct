<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;


use App\Models\RentTransaction;

new class extends Component
{

    use WithPagination;
    #[Computed]
    public function getCompletedTransactions()
    {
        return RentTransaction::with('post', 'postInterest')
            ->whereIn('status', ['completed', 'cancelled'])
            ->where('operator_id', auth()->id())
            ->latest()
            ->paginate(10);
    }
};
?>

<div>
    <x-table>
        <x-table-columns>
            <x-table-column>Commuter</x-table-column>
            <x-table-column>Date Accepted</x-table-column>
            <x-table-column>Status</x-table-column>
        </x-table-columns>
        <x-table-rows>
            @forelse ($this->getCompletedTransactions as $record)
                <x-table-row>
                    <x-table-cell>{{ $record->postInterest->user->name }}</x-table-cell>
                    <x-table-cell>{{ $record->created_at->format('D, M j, Y') }}</x-table-cell>
                    <x-table-cell>
                        @if($record->status === 'completed')
                            <x-badge size="sm" color="green">Completed</x-badge>
                        @elseif($record->status === 'cancelled')
                            <x-badge size="sm" color="yellow">Cancelled</x-badge>
                        @endif
                    </x-table-cell>
                </x-table-row>
            @empty
                <x-table-row>
                    <x-table-cell colspan="3">
                        <x-text variant="subtle">No completed transactions yet.</x-text>
                    </x-table-cell>
                </x-table-row>
            @endforelse
        </x-table-rows>
    </x-table>
    @if ($this->getCompletedTransactions->hasPages())
        <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
            {{ $this->getCompletedTransactions->links() }}
        </div>
    @endif
</div>