<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use App\Models\Card;
use App\Models\CardReport;

new class extends Component
{
    use WithFileUploads;

    public string $reason = '';
    public string $description = '';
    public $valid_id;

    #[Computed]
    public function userCard(): ?Card
    {
        return Card::with('user')
            ->where('user_id', auth()->id())
            ->first();
    }

    #[Computed]
    public function existingPendingReport(): bool
    {
        if (!$this->userCard) return false;
        return CardReport::where('card_id', $this->userCard->id)
            ->where('status', 'pending')
            ->exists();
    }

    #[Computed]
    public function cardReports()
    {
        if (!$this->userCard) return collect();
        return CardReport::with('newCard')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
    }

    public function removeValidId(): void
    {
        $this->reset('valid_id');
    }

    public function submitLostReport(): void
    {
        $this->validate([
            'reason'      => 'required|in:lost,damaged,other',
            'description' => 'required|string|min:10|max:1000',
            'valid_id'    => 'required|image|max:5120',
        ], [
            'reason.required'      => 'Please select a reason.',
            'description.min'      => 'Please describe what happened (at least 10 characters).',
            'valid_id.required'    => 'Please upload a photo of your valid ID.',
            'valid_id.image'       => 'The file must be an image (JPG, PNG, etc.).',
            'valid_id.max'         => 'Image must not exceed 5MB.',
        ]);

        if ($this->existingPendingReport) {
            Flux::toast(variant: 'warning', duration: 4000, heading: 'Report already submitted.', text: 'You already have a pending report for this card.');
            return;
        }

        $path = $this->valid_id->store('card-reports/' . auth()->id(), 'public');

        CardReport::create([
            'user_id'       => auth()->id(),
            'card_id'       => $this->userCard->id,
            'reason'        => $this->reason,
            'description'   => $this->description,
            'valid_id_path' => $path,
            'status'        => 'pending',
        ]);

        $this->reset('reason', 'description', 'valid_id');

        Flux::toast(
            variant: 'success',
            duration: 4000,
            heading: 'Report submitted.',
            text: 'An admin will review your request. Visit the terminal to complete the card replacement.',
        );
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="Card Reports"
        description="Report a lost or damaged card, and track your replacement status."
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
                Card Reports
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Report a lost or damaged card, and track your replacement status.
            </x-text>
        </div>
    </div>

    {{-- Back to My Card --}}
    <div class="mb-6">
        <a
            href="{{ route('user.card') }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
        >
            <flux:icon name="arrow-left" class="w-4 h-4" />
            Back to My Card
        </a>
    </div>

    @if (!$this->userCard)
        <flux:card class="px-6 py-14 text-center dark:bg-dark-secondary dark:border-dark-bd-default">
            <flux:icon name="credit-card" class="w-10 h-10 text-light-txt-muted dark:text-dark-txt-muted mx-auto mb-3" />
            <p class="text-sm text-light-txt-muted dark:text-dark-txt-muted">No card linked to your account yet.</p>
            <p class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">Visit the terminal to get your RFID card issued.</p>
        </flux:card>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Left: Report form --}}
            <div class="lg:col-span-2">
                <x-card class="!p-0 overflow-hidden">
                    <div class="px-4 sm:px-5 py-3 border-b border-light-bd-default dark:border-dark-bd-default bg-light-secondary/50 dark:bg-dark-secondary/50">
                        <h3 class="font-primary text-card-title font-semibold text-light-txt-primary dark:text-dark-txt-primary">
                            Report Lost / Damaged Card
                        </h3>
                    </div>

                    @if ($this->existingPendingReport)
                        <div class="p-4 sm:p-6">
                            <div class="rounded-lg bg-warning/10 dark:bg-dark-warning/20 border border-warning/30 dark:border-dark-warning/30 px-4 py-3 flex items-start gap-3">
                                <flux:icon name="clock" class="w-5 h-5 text-warning dark:text-dark-warning shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-warning dark:text-dark-warning">You have a pending report</p>
                                    <p class="text-xs text-warning/80 dark:text-dark-warning/80 mt-0.5">
                                        Visit the terminal to complete the card replacement. You can submit a new report once this one is resolved.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="p-4 sm:p-6 space-y-5">
                            <flux:text class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                Submit a report and bring a new blank RFID card to the terminal for replacement.
                            </flux:text>

                            <flux:field>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    Reason
                                </flux:label>
                                <flux:select
                                    wire:model="reason"
                                    placeholder="Select a reason…"
                                    size="sm"
                                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                                >
                                    <flux:select.option value="lost">Lost</flux:select.option>
                                    <flux:select.option value="damaged">Damaged</flux:select.option>
                                    <flux:select.option value="other">Other</flux:select.option>
                                </flux:select>
                                <flux:error name="reason" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    Description
                                </flux:label>
                                <flux:textarea
                                    wire:model="description"
                                    placeholder="Describe what happened to your card…"
                                    rows="3"
                                    size="sm"
                                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                                />
                                <flux:error name="description" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                    Valid ID photo
                                </flux:label>

                                @if (!$valid_id)
                                    <label
                                        for="report_valid_id"
                                        class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle px-4 py-6 cursor-pointer hover:border-light-txt-muted dark:hover:border-dark-txt-muted transition-colors mt-1"
                                    >
                                        <flux:icon name="arrow-up-tray" class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                                        <span class="text-sm text-light-txt-body dark:text-dark-txt-body">Upload a photo of your ID</span>
                                        <span class="text-xs text-light-txt-muted dark:text-dark-txt-muted">JPG or PNG, max 5MB</span>
                                        <input
                                            id="report_valid_id"
                                            type="file"
                                            accept="image/*"
                                            wire:model="valid_id"
                                            class="hidden"
                                        />
                                    </label>
                                @endif

                                <div wire:loading wire:target="valid_id" class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">Uploading…</div>

                                @if ($valid_id)
                                    <div class="flex items-center gap-3 rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-light-subtle dark:bg-dark-subtle px-3 py-2 mt-1">
                                        <img
                                            src="{{ $valid_id->temporaryUrl() }}"
                                            alt="Valid ID preview"
                                            class="size-10 rounded-md object-cover shrink-0"
                                        />
                                        <span class="text-sm text-light-txt-body dark:text-dark-txt-primary truncate flex-1">
                                            {{ $valid_id->getClientOriginalName() }}
                                        </span>
                                        <flux:icon name="check-circle" class="size-4 text-success dark:text-dark-success shrink-0" />
                                        <button type="button" wire:click="removeValidId" class="shrink-0 text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-primary">
                                            <flux:icon name="x-mark" class="size-4" />
                                        </button>
                                    </div>
                                @endif

                                <flux:error name="valid_id" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                            </flux:field>

                            <div class="flex justify-end pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                                <flux:button
                                    variant="primary"
                                    wire:click="submitLostReport"
                                    wire:loading.attr="disabled"
                                    wire:target="submitLostReport"
                                    class="w-full sm:w-auto justify-center font-secondary"
                                >
                                    <span wire:loading.remove wire:target="submitLostReport">Submit Report</span>
                                    <span wire:loading wire:target="submitLostReport">Submitting…</span>
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- Right: Report history --}}
            <div class="lg:col-span-1">
                <flux:card class="p-0! overflow-hidden">
                    <div class="px-3 sm:px-4 py-2.5 border-b border-light-bd-default dark:border-dark-bd-default">
                        <p class="font-secondary font-semibold text-sm text-light-txt-primary dark:text-dark-txt-primary">Report History</p>
                        <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted">Your past card reports and their status.</p>
                    </div>

                    <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default max-h-[560px] overflow-y-auto">
                        @forelse ($this->cardReports as $report)
                            <div class="p-3 sm:p-4">
                                <div class="flex items-start gap-3">
                                    {{-- Status icon --}}
                                    @if ($report->status === 'pending')
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-warning/10 dark:bg-dark-warning/20 flex items-center justify-center">
                                            <flux:icon name="clock" class="w-4 h-4 text-warning dark:text-dark-warning" />
                                        </div>
                                    @elseif ($report->status === 'approved')
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-success/10 dark:bg-dark-success/20 flex items-center justify-center">
                                            <flux:icon name="check-circle" class="w-4 h-4 text-success dark:text-dark-success" />
                                        </div>
                                    @else
                                        <div class="shrink-0 w-8 h-8 rounded-full bg-danger/10 dark:bg-dark-danger/20 flex items-center justify-center">
                                            <flux:icon name="x-circle" class="w-4 h-4 text-danger dark:text-dark-danger" />
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <x-text class="text-sm font-medium text-light-txt-body dark:text-dark-txt-primary">
                                                {{ ucfirst($report->reason) }} card report
                                            </x-text>
                                            @if ($report->status === 'pending')
                                                <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Pending review</flux:badge>
                                            @elseif ($report->status === 'approved')
                                                <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Approved</flux:badge>
                                            @else
                                                <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">Rejected</flux:badge>
                                            @endif
                                        </div>

                                        <x-text class="text-[11px] text-light-txt-muted dark:text-dark-txt-muted mt-0.5">
                                            Submitted {{ $report->created_at->format('M d, Y') }}
                                        </x-text>

                                        @if ($report->status === 'approved' && $report->newCard)
                                            <x-text class="text-xs text-success dark:text-dark-success mt-1">
                                                New card issued: <span class="font-mono">**** {{ substr($report->newCard->card_number, -4) }}</span>
                                                · {{ $report->approved_at?->format('M d, Y') }}
                                            </x-text>
                                        @endif

                                        @if ($report->status === 'rejected' && $report->rejection_reason)
                                            <x-text class="text-xs text-danger dark:text-dark-danger mt-1">
                                                Reason: {{ $report->rejection_reason }}
                                            </x-text>
                                        @endif

                                        @if ($report->status === 'pending')
                                            <x-text class="text-xs text-light-txt-muted dark:text-dark-txt-muted mt-1">
                                                Avail a new ICCT card from the cashier (located inside the terminal) to complete the replacement.
                                            </x-text>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-8 gap-2">
                                <flux:icon name="document-text" class="w-6 h-6 text-light-txt-muted dark:text-dark-txt-muted" />
                                <x-text class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                    No reports submitted yet.
                                </x-text>
                            </div>
                        @endforelse
                    </div>
                </flux:card>
            </div>
        </div>
    @endif
</div>