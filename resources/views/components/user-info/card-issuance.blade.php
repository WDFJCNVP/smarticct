<?php

use Livewire\Component;
use Livewire\Attributes\On;

use App\Models\User;
use App\Models\Card;

new class extends Component
{
    public User $user;

    public bool $showCardPanel = false;
    public string $cardUid = '';

    protected $validationAttributes = [
        'cardUid' => 'card UID',
    ];

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    #[On('user-updated')]
    public function refreshUser(): void
    {
        $this->user->refresh();
    }

    /** Fired by the "Issue card now" button in the account banners. */
    #[On('open-card-panel')]
    public function openCardPanel(): void
    {
        $this->showCardPanel = true;
    }

    public function issueCard(): void
    {
        if ($this->user->isDeleted()) {
            Flux::toast(
                variant: 'danger',
                duration: 4000,
                heading: 'Cannot edit this account.',
                text: 'This account was permanently deleted by the user and can no longer be modified.',
            );

            return;
        }

        // A card can only be issued to someone who has an account.
        if (! $this->user->hasAccount()) {
            $this->showCardPanel = false;

            Flux::toast(
                variant: 'warning',
                duration: 4000,
                heading: 'Create an account first.',
                text: 'This operator needs an account before a card can be issued.',
            );

            return;
        }

        $this->cardUid = strtoupper(trim($this->cardUid));

        $this->validate([
            'cardUid' => ['required', 'string', 'unique:cards,uid'],
        ], [
            'cardUid.unique' => 'This card UID is already assigned to another user.',
            'cardUid.regex'  => 'That doesn\'t look like a valid card tap — expected an 8-character UID. Please tap again.',
        ]);

        Card::create([
            'user_id' => $this->user->id,
            'uid'     => $this->cardUid,
            'balance' => 0,
        ]);

        $this->showCardPanel = false;
        $this->cardUid = '';

        $this->user->refresh();
        $this->dispatch('user-updated');

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Card issued.',
            text: 'RFID card has been linked to ' . $this->user->name . '.',
        );
    }
};
?>

<div>
    @if ($showCardPanel && $this->user->hasAccount())
        <div class="mt-3 rounded-xl border border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary overflow-hidden">

            <div class="flex items-center gap-2 px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-secondary">
                <flux:icon name="credit-card" class="w-5 h-5 text-light-txt-muted dark:text-dark-txt-muted shrink-0" />
                <span class="font-primary font-bold text-light-txt-primary dark:text-dark-txt-primary" style="font-size: var(--text-card-title)">
                    Issue RFID card
                </span>
                <div class="flex-1"></div>
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="x-mark"
                    wire:click="$set('showCardPanel', false)"
                    aria-label="Close"
                />
            </div>

            {{-- Scan state banner --}}
            <div @class([
                'flex items-start gap-3 px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default',
                'bg-info/10 dark:bg-dark-info/10'       => empty($cardUid),
                'bg-success/10 dark:bg-dark-success/10' => !empty($cardUid),
            ])>
                <flux:icon
                    :name="empty($cardUid) ? 'credit-card' : 'check-circle'"
                    @class([
                        'w-5 h-5 shrink-0 mt-0.5',
                        'text-info dark:text-dark-info'       => empty($cardUid),
                        'text-success dark:text-dark-success' => !empty($cardUid),
                    ])
                />
                <div class="min-w-0 space-y-0.5">
                    @if (empty($cardUid))
                        <p class="font-secondary font-semibold text-sm text-info dark:text-dark-info">Ready to scan</p>
                        <p class="font-secondary text-helper leading-snug text-info dark:text-dark-info">
                            Hold the new RFID card near the reader — the UID fills in automatically.
                        </p>
                    @else
                        <p class="font-secondary font-semibold text-sm text-success dark:text-dark-success">Card detected</p>
                        <p class="font-secondary text-helper leading-snug text-success dark:text-dark-success break-all">
                            UID {{ $cardUid }} captured. Review the assignment below then click Assign card.
                        </p>
                    @endif
                </div>
            </div>

            <div class="p-4 sm:p-5 space-y-4">
                <flux:field>
                    <flux:label class="flex items-center gap-1.5 font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                        <flux:icon name="credit-card" class="w-3.5 h-3.5" />
                        Card UID
                    </flux:label>

                    <div class="relative mt-1">
                        {{-- Real capture field: invisible but focused, so the RFID reader's
                             keyboard-wedge input lands here instead of showing raw characters. --}}
                        <input
                            type="text"
                            id="card-uid-input"
                            wire:model.live.debounce.200ms="cardUid"
                            autocomplete="off"
                            autofocus            
                            x-ref="cardUidInput"
                            @keydown.enter.prevent
                            class="absolute inset-0 w-full h-full opacity-0 cursor-default"
                        />

                        <div
                            class="pointer-events-none rounded-lg border-2 border-dashed p-4 flex items-center justify-center gap-2 transition-colors
                                {{ $cardUid ? 'border-success dark:border-dark-success bg-success/5 dark:bg-dark-success/10' : 'border-light-bd-default dark:border-dark-bd-default' }}"
                        >
                            @if ($cardUid)
                                <flux:icon name="check-circle" class="w-5 h-5 text-success dark:text-dark-success shrink-0" />
                                <p class="font-mono text-base tracking-[0.3em] font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                                    {{ strtoupper($cardUid) }}
                                </p>
                            @else
                                <flux:icon name="wifi" class="w-5 h-5 text-light-txt-muted dark:text-dark-txt-muted shrink-0 animate-pulse" />
                                <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">Waiting for your tap… please tap only once</p>
                            @endif
                        </div>
                    </div>

                    <flux:error name="cardUid" />

                    @if ($cardUid)
                        <button
                            type="button"
                            wire:click="$set('cardUid', '')"
                            x-on:click="$nextTick(() => $refs.cardUidInput.focus())"
                            class="mt-2 font-secondary text-xs text-primary hover:underline"
                        >
                            Not this card? Tap again
                        </button>
                    @else
                        <flux:description class="font-secondary text-helper text-light-txt-muted dark:text-dark-txt-muted">
                            The UID is captured automatically by the RFID reader. Do not type this manually.
                        </flux:description>
                    @endif
                </flux:field>

                {{-- Assignment preview --}}
                @if (!empty($cardUid))
                    <div class="rounded-lg bg-light-subtle dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default p-3">
                        <p class="font-secondary font-medium uppercase tracking-wide text-nav-label text-light-txt-muted dark:text-dark-txt-muted mb-2">
                            Card will be assigned to
                        </p>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <flux:avatar name="{{ $user->name }}" size="sm" class="shrink-0" />
                                <div class="min-w-0">
                                    <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $user->name }}</p>
                                    <p class="font-secondary font-mono text-helper text-light-txt-muted dark:text-dark-txt-muted truncate">{{ $user->user_code }} · {{ ucfirst($user->role) }}</p>
                                </div>
                            </div>
                            <div class="sm:text-right shrink-0">
                                <p class="font-secondary text-nav-label uppercase tracking-wide text-light-txt-muted dark:text-dark-txt-muted">UID</p>
                                <p class="font-mono font-semibold text-sm text-info dark:text-dark-info break-all">{{ $cardUid }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row gap-2 pt-1">
                    <flux:button
                        type="button"
                        class="flex-1 font-secondary py-2! text-sm! lg:text-md!"
                        wire:click="$set('showCardPanel', false)"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        type="button"
                        variant="primary"
                        icon="credit-card"
                        class="flex-1 font-secondary py-2! text-sm! lg:text-md!"
                        wire:click="issueCard"
                        wire:loading.attr="disabled"
                        wire:target="issueCard"
                        :disabled="empty($cardUid)"
                    >
                        Assign card to user
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>