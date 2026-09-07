<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Models\Card;
use App\Models\User;

new #[Layout('layouts.admin-layout')] class extends Component
{
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

        $issuedTo = $user->name;

        $this->resetIssueCardForm();

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Card issued.',
            text: 'A new card has been assigned to ' . $issuedTo . '.',
        );
    }

    // Latest cards issued to commuters/operators.
    #[Computed]
    public function recentIssuances()
    {
        return Card::with('user')
            ->whereHas('user', fn($q) => $q->whereIn('role', ['operator', 'commuter']))
            ->latest()
            ->take(8)
            ->get();
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="Issue New Card"
        description="Scan a blank RFID card, then assign it to an operator or commuter."
        class="mb-4"
    >
        {{-- No extra controls --}}
    </x-page-header>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex items-start justify-between gap-4 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Issue New Card
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Scan a blank RFID card, then assign it to an operator or commuter.
            </x-text>
        </div>
    </div>

    {{-- Back to Cards --}}
    <div class="mb-6">
        <a
            href="{{ route('admin.cards') }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
        >
            <flux:icon name="arrow-left" class="w-4 h-4" />
            Back to Cards
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- Left: Issue card form --}}
        <div class="lg:col-span-2">
            <x-card class="!p-0 overflow-hidden">
                <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                    <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                        Card details
                    </h3>
                </div>

                <div class="p-4 sm:p-6 space-y-5">
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
                            autofocus
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
                                <div class="absolute z-20 w-full mt-1 bg-light-primary dark:bg-dark-surface border border-light-bd-default dark:border-dark-bd-default rounded-lg shadow-xl max-h-52 overflow-y-auto">
                                    @forelse ($this->issueCandidates as $candidate)
                                        <div
                                            wire:click="selectIssueUser({{ $candidate->id }})"
                                            class="flex items-center gap-3 px-3 py-2.5 hover:bg-light-subtle dark:hover:bg-dark-subtle cursor-pointer"
                                        >
                                            <flux:avatar size="xs" src="{{ $candidate->avatar_url }}" name="{{ $candidate->name }}" />
                                            <div class="min-w-0">
                                                <p class="font-secondary text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $candidate->name }}</p>
                                                <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">{{ $candidate->user_code }} · {{ ucfirst($candidate->role) }}</p>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="px-3 py-2 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">No cardless users found.</div>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                        <flux:error name="issueUserId" />
                    </flux:field>

                    {{-- Assignment preview --}}
                    @if ($this->issueSelectedUser)
                        <div class="rounded-lg bg-light-subtle dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default p-4">
                            <p class="font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted mb-2">
                                Card will be assigned to
                            </p>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-3 min-w-0">
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

                    <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-4 border-t border-light-bd-default dark:border-dark-bd-default">
                        <flux:button
                            type="button"
                            variant="ghost"
                            wire:click="resetIssueCardForm"
                            class="w-full sm:w-auto justify-center font-secondary"
                        >
                            Clear
                        </flux:button>
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
            </x-card>
        </div>

        {{-- Right: Card Issuance history --}}
        <div class="lg:col-span-1">
            <flux:card class="p-0! overflow-hidden">
                <div class="px-3 sm:px-4 py-2.5 border-b border-light-bd-default dark:border-dark-bd-default">
                    <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary">Card Issuance</p>
                    <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Latest cards issued to registered commuters and operators.</p>
                </div>
                <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default max-h-[560px] overflow-y-auto">
                    @forelse ($this->recentIssuances as $card)
                        <div class="flex items-center gap-3 px-3 sm:px-4 py-3">
                            <flux:avatar size="sm" src="{{ $card->user?->avatar_url }}" name="{{ $card->user?->name }}" class="shrink-0" />
                            <div class="min-w-0 flex-1">
                                <p class="font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-body truncate">{{ $card->user?->name ?? 'Unknown' }}</p>
                                <p class="font-secondary font-mono text-xs text-light-txt-muted dark:text-dark-txt-muted tracking-widest">
                                    **** {{ substr($card->card_number ?? '----', -4) }}
                                </p>
                                <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted tabular-nums">
                                    {{ $card->created_at->format('M d, Y g:i a') }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <flux:badge size="sm" color="{{ $card->user?->role === 'operator' ? 'blue' : 'amber' }}" class="font-secondary text-badge text-xs">
                                    {{ ucfirst($card->user?->role ?? '—') }}
                                </flux:badge>
                                @if ($card->status === 'active')
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Active</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">{{ ucfirst($card->status) }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-8 gap-2">
                            <flux:icon.credit-card class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                            <x-text class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                No cards have been issued yet.
                            </x-text>
                        </div>
                    @endforelse
                </div>
            </flux:card>
        </div>
    </div>
</div>