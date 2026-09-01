<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\User;
use App\Models\Card;
use App\Models\CardReport;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public $search;
    public string $reportFilter = 'pending';

    // For the report detail modal
    public ?int $selectedReportId = null;
    public string $rejectionReason = '';

    // NEW: store the new card UID entered in the modal
    public ?string $newCardUid = null;

    // ===================== ISSUE NEW CARD =====================

    public string $issueCardUid = '';
    public string $issueUserSearch = '';
    public ?int $issueUserId = null;

    #[Computed]
    public function issueCandidates()
    {
        if (strlen($this->issueUserSearch) < 2) {
            return collect();
        }

        return User::whereIn('role', ['operator', 'commuter'])
            ->whereDoesntHave('card')
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->issueUserSearch . '%')
                  ->orWhere('user_code', 'like', '%' . $this->issueUserSearch . '%');
            })
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function issueSelectedUser(): ?User
    {
        return $this->issueUserId ? User::find($this->issueUserId) : null;
    }

    public function openIssueCardModal(): void
    {
        $this->issueCardUid = '';
        $this->issueUserSearch = '';
        $this->issueUserId = null;
        $this->resetValidation();
        $this->dispatch('open-issue-card-modal');
    }

    public function resetIssueCardForm(): void
    {
        $this->issueCardUid = '';
        $this->issueUserSearch = '';
        $this->issueUserId = null;
        $this->resetValidation();
    }

    public function selectIssueUser(int $userId): void
    {
        $this->issueUserId = $userId;
        $this->issueUserSearch = User::find($userId)?->name ?? '';
    }

    public function clearIssueUser(): void
    {
        $this->issueUserId = null;
        $this->issueUserSearch = '';
    }

    public function issueNewCard(): void
    {
        $this->validate([
            'issueCardUid' => 'required|string|min:4|unique:cards,uid',
            'issueUserId'  => 'required|integer|exists:users,id',
        ], [
            'issueCardUid.unique'   => 'This card UID is already assigned to another user.',
            'issueCardUid.min'      => 'The scanned UID looks too short — please scan again.',
            'issueUserId.required'  => 'Please select who this card is for.',
        ]);

        $user = User::findOrFail($this->issueUserId);

        if ($user->card) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'User already has a card.',
                text: $user->name . ' already has a card on file.',
            );
            return;
        }

        Card::create([
            'user_id' => $user->id,
            'uid'     => $this->issueCardUid,
            'balance' => 0,
        ]);

        $this->issueCardUid = '';
        $this->issueUserSearch = '';
        $this->issueUserId = null;
        $this->dispatch('close-issue-card-modal');

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Card issued.',
            text: 'A new card has been assigned to ' . $user->name . '.',
        );
    }

    #[Computed]
    public function getUsers() {
        return User::with('card')
            ->whereIn('role', ['operator', 'commuter'])
            ->whereHas('card')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('user_code', 'like', '%' . $this->search . '%')
                      ->orWhereHas('card', fn($q) =>
                          $q->where('card_number', 'like', '%' . $this->search . '%')
                      );
                });
            })
            ->paginate(10);
    }

    // ===================== EXPORT MODAL =====================
    public string $exportStatus = '';

    #[Computed]
    public function exportUrl(): string
    {
        return route('admin.cards.export', array_filter([
            'search' => $this->search,
            'status' => $this->exportStatus,
        ]));
    }

    #[Computed]
    public function cardStats() {
        $cards = Card::whereHas('user', fn($q) => $q->whereIn('role', ['operator', 'commuter']));
        return [
            'total'    => $cards->count(),
            'active'   => (clone $cards)->where('status', 'active')->count(),
            'inactive' => (clone $cards)->where('status', '!=', 'active')->count(),
        ];
    }

    // Latest cards issued to commuters/operators — a quick "who just got a
    // card" history strip, separate from the full searchable list above.
    #[Computed]
    public function recentIssuances() {
        return Card::with('user')
            ->whereHas('user', fn($q) => $q->whereIn('role', ['operator', 'commuter']))
            ->latest()
            ->take(8)
            ->get();
    }

    #[Computed]
    public function cardReports() {
        return CardReport::with(['user', 'card', 'approvedBy'])
            ->when($this->reportFilter !== 'all', fn($q) => $q->where('status', $this->reportFilter))
            ->latest()
            ->get();
    }

    #[Computed]
    public function pendingReportCount() {
        return CardReport::where('status', 'pending')->count();
    }

    #[Computed]
    public function selectedReport() {
        if (!$this->selectedReportId) return null;
        return CardReport::with(['user', 'card', 'approvedBy', 'newCard'])->find($this->selectedReportId);
    }

    public function viewReport(int $reportId): void
    {
        $this->selectedReportId = $reportId;
        $this->rejectionReason = '';
        $this->newCardUid = '';
        $this->dispatch('open-report-modal');
    }

    public function approveReport(): void
    {
        $report = CardReport::with(['card', 'user'])->findOrFail($this->selectedReportId);

        if ($report->status !== 'pending') {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Already processed.', text: 'This report has already been handled.');
            return;
        }

        if (empty($this->newCardUid)) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'New card UID required.',
                text: 'Please enter the UID of the new blank card.'
            );
            return;
        }

        if (Card::where('uid', $this->newCardUid)->exists()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Duplicate UID.',
                text: 'A card with that UID already exists in the system.'
            );
            return;
        }

        // Declare variable outside transaction so we can use it after
        $newCard = null;

        DB::transaction(function () use ($report, &$newCard) {
            $oldCard = $report->card;

            // Create the new card for the same user
            $newCard = Card::create([
                'user_id'     => $oldCard->user_id,
                'uid'         => $this->newCardUid,
                'balance'     => $oldCard->balance,
                'status'      => 'active',
                // card_number auto-generated by boot()
            ]);

            // Suspend the old card
            $oldCard->update(['status' => 'suspended']);

            // Close the report
            $report->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'new_card_id' => $newCard->id,
                'approved_at' => now(),
            ]);
        });

        // Notify the commuter
        $notification = Notification::create([
            'type'    => 'Card',
            'title'   => 'Replacement Card Issued',
            'message' => 'Your lost card report has been approved. Your new card is now active and your balance has been transferred. Please collect your new card at the terminal.',
            'metadata' => [
                'report_id'   => $report->id,
                'new_card_id' => $newCard->id,
            ],
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $report->user_id,
        ]);

        broadcast(new NotificationEvent());

        $this->selectedReportId = null;
        $this->dispatch('close-report-modal');

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Replacement issued.',
            text: 'Old card suspended. New card created with balance transferred.',
        );
    }

    public function rejectReport(): void
    {
        $this->validate(['rejectionReason' => 'required|string|min:5|max:500'], [
            'rejectionReason.required' => 'Please provide a reason for rejection.',
            'rejectionReason.min'      => 'Reason must be at least 5 characters.',
        ]);

        $report = CardReport::findOrFail($this->selectedReportId);

        if ($report->status !== 'pending') {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Already processed.');
            return;
        }

        $report->update([
            'status'           => 'rejected',
            'approved_by'      => auth()->id(),
            'rejection_reason' => $this->rejectionReason,
            'approved_at'      => now(),
        ]);

        // Notify the commuter
        $notification = Notification::create([
            'type'    => 'Card',
            'title'   => 'Lost Card Report Rejected',
            'message' => 'Your lost card report has been rejected. Reason: ' . $this->rejectionReason . '. Please visit the terminal or contact support if you have questions.',
            'metadata' => [
                'report_id'        => $report->id,
                'rejection_reason' => $this->rejectionReason,
            ],
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $report->user_id,
        ]);

        broadcast(new NotificationEvent());

        $this->selectedReportId = null;
        $this->rejectionReason = '';
        $this->dispatch('close-report-modal');

        Flux::toast(variant: 'success', duration: 4000, heading: 'Report rejected.', text: 'The commuter has been notified.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    {{-- Header – consistent with other pages --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Cards
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                View all registered cards and manage lost card reports.
            </x-text>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 shrink-0">
            <flux:modal.trigger name="export-card-inventory">
                <button
                    type="button"
                    class="flex items-center gap-1.5 sm:gap-2 px-3 h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle transition font-secondary text-xs sm:text-table-row shrink-0 w-full sm:w-auto justify-center"
                >
                    <flux:icon.arrow-down-tray class="w-3.5 h-3.5 text-light-txt-muted dark:text-dark-txt-muted" />
                    <span>Export Inventory</span>
                </button>
            </flux:modal.trigger>

            <flux:button
                variant="primary"
                icon="credit-card"
                wire:click="openIssueCardModal"
                class="font-secondary shrink-0"
            >
                Issue New Card
            </flux:button>
        </div>
    </div>

    {{-- ===================== EXPORT MODAL ===================== --}}
    <flux:modal
        name="export-card-inventory"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Export card inventory
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Uses the search box above, plus an optional status filter. Leave status blank to include all cards.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Status</flux:label>
                <flux:select
                    wire:model.live="exportStatus"
                    size="sm"
                    placeholder="All statuses"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    <flux:select.option value="">All statuses</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="suspended">Suspended</flux:select.option>
                    <flux:select.option value="terminated">Terminated</flux:select.option>
                </flux:select>
            </flux:field>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    href="{{ $this->exportUrl }}"
                    icon="arrow-down-tray"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Download PDF
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Stats cards – same pattern as users / travel record --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-6">
        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                    <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Total cards
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-light-txt-primary dark:text-dark-txt-primary block">
                {{ $this->cardStats['total'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-success/10 dark:bg-dark-success/20 shrink-0">
                    <flux:icon.check-circle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-success dark:text-dark-success" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Active
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-success dark:text-dark-success block">
                {{ $this->cardStats['active'] }}
            </x-text>
        </flux:card>

        <flux:card class="p-3 sm:p-4">
            <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-danger/10 dark:bg-dark-danger/20 shrink-0">
                    <flux:icon.exclamation-triangle class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-danger dark:text-dark-danger" />
                </div>
                <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                    Inactive / suspended
                </x-text>
            </div>
            <x-text class="font-primary text-stat-value font-bold text-danger dark:text-dark-danger block">
                {{ $this->cardStats['inactive'] }}
            </x-text>
        </flux:card>
    </div>

    {{-- Search – dark mode classes added --}}
    <div class="flex items-center gap-3 mb-4">
        <flux:input
            class="max-w-xs font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            size="sm"
            icon="magnifying-glass"
            placeholder="Search name, ID, card…"
            wire:model.live.debounce.300ms="search"
        />
    </div>

    {{-- Cards table – standard card with p-0 and sticky headers --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Owner ID</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Card no.</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Name</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Balance</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Registered</flux:table.column>
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getUsers as $index => $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ ($this->getUsers->currentPage() - 1) * $this->getUsers->perPage() + $index + 1 }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->user_code }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <span class="font-mono text-xs md:text-table-row tracking-widest text-light-txt-muted dark:text-dark-txt-muted">
                                    **** **** **** {{ substr($user->card->card_number, -4) }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-2">
                                    <flux:avatar size="xs" src="{{ $user->avatar_url }}" name="{{ $user->name }}" />
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">{{ $user->name }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                {{ number_format($user->card->balance, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                @if ($user->card->status === 'active')
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Active</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">{{ ucfirst($user->card->status) }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                {{ $user->card->created_at->format('Y-m-d') }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2">
                                <flux:link href="/admin/card/transaction/{{ $user->id }}" wire:navigate>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" class="scale-75 md:scale-100" />
                                </flux:link>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.credit-card class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No cards found.
                                    </x-text>
                                    @if ($search)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            Try a different search term.
                                        </x-text>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getUsers->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getUsers->links() }}
            </div>
        @endif
    </flux:card>

    {{-- Card Issuance Section — recent history of newly issued cards --}}
    <div class="mt-10">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <x-heading
                    size="lg"
                    class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                >
                    Card Issuance
                </x-heading>
                <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                    Latest cards issued to registered commuters and operators.
                </x-text>
            </div>
        </div>

        <flux:card class="p-0! overflow-hidden">
            <div class="overflow-x-auto">
                <flux:table container:class="max-h-100">
                    <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                        <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Owner ID</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Name</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Role</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Card no.</flux:table.column>
                        <flux:table.column align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-2">UID</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Issued</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->recentIssuances as $index => $card)
                            <flux:table.row :key="$card->id">
                                <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $index + 1 }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $card->user?->user_code ?? '—' }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    <div class="flex items-center justify-center gap-2">
                                        <flux:avatar size="xs" src="{{ $card->user?->avatar_url }}" name="{{ $card->user?->name }}" />
                                        <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">{{ $card->user?->name ?? 'Unknown' }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    <flux:badge size="sm" color="{{ $card->user?->role === 'operator' ? 'blue' : 'amber' }}" class="font-secondary text-badge text-xs">
                                        {{ ucfirst($card->user?->role ?? '—') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    <span class="font-mono text-xs md:text-table-row tracking-widest text-light-txt-muted dark:text-dark-txt-muted">
                                        **** **** **** {{ substr($card->card_number ?? '----', -4) }}
                                    </span>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="hidden md:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $card->uid }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    @if ($card->status === 'active')
                                        <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Active</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">{{ ucfirst($card->status) }}</flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                    {{ $card->created_at->format('M d, Y g:i a') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="px-2 md:px-4 py-4">
                                    <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                        <flux:icon.credit-card class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                        <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                            No cards have been issued yet.
                                        </x-text>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </div>

    {{-- Card Reports Section --}}
    <div class="mt-10">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <x-heading
                    size="lg"
                    class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                >
                    Card Reports
                </x-heading>
                @if ($this->pendingReportCount > 0)
                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">{{ $this->pendingReportCount }} pending</flux:badge>
                @endif
            </div>
            <flux:select
                wire:model.live="reportFilter"
                size="sm"
                class="w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="pending">Pending</flux:select.option>
                <flux:select.option value="approved">Approved</flux:select.option>
                <flux:select.option value="rejected">Rejected</flux:select.option>
                <flux:select.option value="all">All</flux:select.option>
            </flux:select>
        </div>

        {{-- Reports table – also with p-0 and sticky headers --}}
        <flux:card class="p-0! overflow-hidden">
            <div class="overflow-x-auto">
                <flux:table container:class="md:max-h-160">
                    <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                        <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">#</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Commuter</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Reason</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Submitted</flux:table.column>
                        <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2">Status</flux:table.column>
                        <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->cardReports as $index => $report)
                            <flux:table.row :key="$report->id">
                                <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $index + 1 }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    <div class="flex items-center justify-center gap-2">
                                        <flux:avatar size="xs" src="{{ $report->user->avatar_url }}" name="{{ $report->user->name }}" />
                                        <div class="text-left">
                                            <p class="font-secondary text-xs md:text-table-row font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $report->user->name }}</p>
                                            <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $report->user->user_code }}</p>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    <flux:badge size="sm" color="{{ match($report->reason) { 'lost' => 'red', 'damaged' => 'yellow', default => 'zinc' } }}" class="font-secondary text-badge text-xs">
                                        {{ ucfirst($report->reason) }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                    {{ $report->created_at->format('M d, Y') }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                    @if ($report->status === 'pending')
                                        <flux:badge color="yellow" size="sm" icon="clock" class="font-secondary text-badge text-xs">Pending</flux:badge>
                                    @elseif ($report->status === 'approved')
                                        <flux:badge color="green" size="sm" icon="check-circle" class="font-secondary text-badge text-xs">Approved</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm" icon="x-circle" class="font-secondary text-badge text-xs">Rejected</flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2">
                                    <flux:button
                                        wire:click="viewReport({{ $report->id }})"
                                        variant="ghost"
                                        size="sm"
                                        icon="ellipsis-horizontal"
                                        inset="top bottom"
                                        class="scale-75 md:scale-100"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6" class="px-2 md:px-4 py-4">
                                    <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                        <flux:icon.document-text class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                        <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                            No {{ $reportFilter === 'all' ? '' : $reportFilter }} reports found.
                                        </x-text>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </div>

    {{-- Report Detail Modal – consistent with other modals --}}
    <flux:modal
        name="report-detail-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl mx-auto rounded-xl overflow-hidden"
        x-on:open-report-modal.window="$flux.modal('report-detail-modal').show()"
        x-on:close-report-modal.window="$flux.modal('report-detail-modal').close()"
    >
        @if ($this->selectedReport)
            @php $report = $this->selectedReport; @endphp
            <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">

                {{-- Header --}}
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                            Lost Card Report
                        </flux:heading>
                        <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                            Submitted {{ $report->created_at->format('M d, Y · h:i A') }}
                        </flux:text>
                    </div>
                    <flux:modal.close>
                        <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                            <flux:icon name="x-mark" class="w-5 h-5" />
                        </button>
                    </flux:modal.close>
                </div>

                {{-- Commuter info --}}
                <div class="flex items-center gap-3 p-3 rounded-lg bg-light-subtle dark:bg-dark-subtle">
                    <flux:avatar src="{{ $report->user->avatar_url }}" name="{{ $report->user->name }}" />
                    <div>
                        <p class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $report->user->name }}</p>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $report->user->user_code }}</p>
                    </div>
                    <div class="ml-auto text-right">
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Card no.</p>
                        <p class="font-mono text-xs tracking-widest text-light-txt-primary dark:text-dark-txt-primary">**** {{ substr($report->card->card_number, -4) }}</p>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mt-0.5">Balance: <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">₱{{ number_format($report->card->balance, 2) }}</span></p>
                    </div>
                </div>

                {{-- Reason & status --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mb-1">Reason</p>
                        <flux:badge size="sm" color="{{ match($report->reason) { 'lost' => 'red', 'damaged' => 'yellow', default => 'zinc' } }}" class="font-secondary text-badge text-xs">
                            {{ ucfirst($report->reason) }}
                        </flux:badge>
                    </div>
                    <div>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mb-1">Status</p>
                        @if ($report->status === 'pending')
                            <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Pending</flux:badge>
                        @elseif ($report->status === 'approved')
                            <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Approved</flux:badge>
                        @else
                            <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Rejected</flux:badge>
                        @endif
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mb-1">Description</p>
                    <p class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body bg-light-subtle dark:bg-dark-subtle rounded-lg p-3">
                        {{ $report->description }}
                    </p>
                </div>

                {{-- Valid ID photo --}}
                @if ($report->valid_id_path)
                    <div>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted mb-2">Valid ID submitted</p>
                        <img
                            src="{{ Storage::url($report->valid_id_path) }}"
                            alt="Valid ID"
                            class="w-full rounded-lg border border-light-bd-default dark:border-dark-bd-default object-cover max-h-52"
                        />
                    </div>
                @endif

                {{-- Approved info --}}
                @if ($report->status === 'approved')
                    <div class="rounded-lg border border-success/20 dark:border-dark-success/20 bg-success/10 dark:bg-dark-success/10 p-3 text-sm text-success dark:text-dark-success">
                        Approved by <strong>{{ $report->approvedBy?->name ?? 'Admin' }}</strong>
                        on {{ $report->approved_at?->format('M d, Y') }}.
                        New card: <span class="font-mono">**** {{ substr($report->newCard?->card_number, -4) }}</span>
                    </div>
                @endif

                {{-- Rejection info --}}
                @if ($report->status === 'rejected')
                    <div class="rounded-lg border border-danger/20 dark:border-dark-danger/20 bg-danger/10 dark:bg-dark-danger/10 p-3 text-sm text-danger dark:text-dark-danger">
                        <p class="font-medium mb-1">Rejection reason:</p>
                        <p>{{ $report->rejection_reason }}</p>
                    </div>
                @endif

                {{-- New Card UID + Rejection reason (only when pending) --}}
                @if ($report->status === 'pending')
                    <div>
                        <flux:input
                            wire:model="newCardUid"
                            label="New Card UID"
                            placeholder="Enter the UID of the blank card"
                            class="font-mono font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                        />
                        <flux:error name="newCardUid" />
                        <p class="mt-1 text-xs text-light-txt-muted dark:text-dark-txt-muted">
                            This UID will be used to create a new card for the commuter.
                        </p>
                    </div>

                    <div>
                        <flux:textarea
                            wire:model="rejectionReason"
                            label="Rejection reason (required to reject)"
                            placeholder="Explain why the report is being rejected…"
                            rows="2"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                        />
                        <flux:error name="rejectionReason" />
                    </div>
                @endif

                {{-- Action buttons --}}
                <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close class="w-full sm:w-auto">
                        <flux:button variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                            Close
                        </flux:button>
                    </flux:modal.close>

                    @if ($report->status === 'pending')
                        <flux:button
                            variant="danger"
                            wire:click="rejectReport"
                            wire:loading.attr="disabled"
                            wire:target="rejectReport"
                            wire:confirm="Reject this report? The commuter will need to re-submit."
                            class="w-full sm:w-auto justify-center font-secondary"
                        >
                            <span wire:loading.remove wire:target="rejectReport">Reject</span>
                            <span wire:loading wire:target="rejectReport">Rejecting…</span>
                        </flux:button>
                        <flux:button
                            variant="primary"
                            wire:click="approveReport"
                            wire:loading.attr="disabled"
                            wire:target="approveReport"
                            wire:confirm="Approve and issue replacement? The new card will be created with the provided UID."
                            class="w-full sm:w-auto justify-center font-secondary"
                        >
                            <span wire:loading.remove wire:target="approveReport">Approve & Issue Replacement</span>
                            <span wire:loading wire:target="approveReport">Processing…</span>
                        </flux:button>
                    @endif
                </div>

            </div>
        @endif
    </flux:modal>

    {{-- Issue New Card Modal --}}
    <flux:modal
        name="issue-new-card-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg mx-auto rounded-xl overflow-hidden"
        x-on:open-issue-card-modal.window="$flux.modal('issue-new-card-modal').show()"
        x-on:close-issue-card-modal.window="$flux.modal('issue-new-card-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Issue new card
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Scan a blank RFID card, then search for the operator or commuter it should be assigned to.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" wire:click="resetIssueCardForm" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="flex items-center gap-1.5 font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                    Card UID
                </flux:label>
                <flux:input
                    wire:model.live="issueCardUid"
                    placeholder="Tap the card on the reader..."
                    autocomplete="off"
                    class="font-mono tracking-widest"
                />
                <flux:error name="issueCardUid" />
                <flux:description class="font-secondary text-helper text-light-txt-muted dark:text-dark-txt-muted">
                    The UID is captured automatically by the RFID reader. Do not type this manually.
                </flux:description>
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary flex items-center gap-1.5">
                    <flux:icon name="magnifying-glass" class="w-3.5 h-3.5" />
                    Assign to
                </flux:label>
                <div class="relative mt-1">
                    <flux:input
                        wire:model.live.debounce.300ms="issueUserSearch"
                        placeholder="Search name or user code…"
                        autocomplete="off"
                        :disabled="(bool) $issueUserId"
                    />
                    @if (strlen($issueUserSearch) >= 2 && !$issueUserId)
                        <div class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl max-h-52 overflow-y-auto">
                            @forelse ($this->issueCandidates as $candidate)
                                <div
                                    wire:click="selectIssueUser({{ $candidate->id }})"
                                    class="flex items-center gap-3 px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer"
                                >
                                    <flux:avatar size="xs" src="{{ $candidate->avatar_url }}" name="{{ $candidate->name }}" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100 truncate">{{ $candidate->name }}</p>
                                        <p class="text-xs text-zinc-400">{{ $candidate->user_code }} · {{ ucfirst($candidate->role) }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="px-3 py-2 text-sm text-zinc-400">No cardless users found.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
                <flux:error name="issueUserId" />
            </flux:field>

            {{-- Assignment preview --}}
            @if ($this->issueSelectedUser)
                <div class="rounded-lg bg-light-subtle dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default p-3">
                    <p class="font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted mb-2">
                        Card will be assigned to
                    </p>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <flux:avatar name="{{ $this->issueSelectedUser->name }}" size="sm" class="shrink-0" />
                            <div class="min-w-0">
                                <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $this->issueSelectedUser->name }}</p>
                                <p class="font-secondary font-mono text-helper text-light-txt-muted dark:text-dark-txt-muted truncate">{{ $this->issueSelectedUser->user_code }} · {{ ucfirst($this->issueSelectedUser->role) }}</p>
                            </div>
                        </div>
                        <button type="button" wire:click="clearIssueUser" class="text-light-txt-muted hover:text-danger dark:hover:text-dark-danger transition shrink-0">
                            <flux:icon name="x-mark" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            @endif

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" wire:click="resetIssueCardForm" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="credit-card"
                    wire:click="issueNewCard"
                    wire:loading.attr="disabled"
                    wire:target="issueNewCard"
                    :disabled="empty($issueCardUid) || !$issueUserId"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    <span wire:loading.remove wire:target="issueNewCard">Issue card</span>
                    <span wire:loading wire:target="issueNewCard">Issuing…</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>