<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

use App\Models\User;
use App\Models\Card;

new #[Layout('layouts.admin-layout')] class extends Component
{
    use WithPagination;

    public $search;
    public string $statusFilter = '';
    public string $sortBy = 'newest';

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
            ->when($this->statusFilter, function ($query) {
                $query->whereHas('card', fn ($q) => $q->where('status', $this->statusFilter));
            })
            ->tap(function ($query) {
                match ($this->sortBy) {
                    'name_asc'  => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    'balance'   => $query->orderBy(
                        \App\Models\Card::select('balance')->whereColumn('user_id', 'users.id')->take(1),
                        'desc'
                    ),
                    'oldest'    => $query->oldest(),
                    default     => $query->latest(),
                };
            })
            ->paginate(10);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'sortBy']);
        $this->sortBy = 'newest';
        $this->resetPage();
    }

    #[Computed]
    public function activeFilterCount(): int
    {
        return collect([$this->search, $this->statusFilter])
            ->filter()
            ->count();
    }

    // ===================== EXPORT MODAL =====================
    public string $exportStatus = '';
    public string $exportPaper = 'legal';
    public string $exportOrientation = 'portrait';

    #[Computed]
    public function exportUrl(): string
    {
        return route('admin.cards.export', array_filter([
            'search'      => $this->search,
            'status'      => $this->exportStatus,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
        ]));
    }

    // Same params as exportUrl, plus preview=1 so the controller streams the
    // PDF inline instead of forcing a download or logging it as an export.
    #[Computed]
    public function exportPreviewUrl(): string
    {
        return route('admin.cards.export', array_filter([
            'search'      => $this->search,
            'status'      => $this->exportStatus,
            'paper'       => $this->exportPaper,
            'orientation' => $this->exportOrientation,
            'preview'     => 1,
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }
};
?>

<div>
    {{-- ====== PAGE HEADER (mini-navbar: heading left, notifications right) ====== --}}
    <x-page-header
        heading="Cards"
        description="Registered Cards & Reports"
        class="mb-4"
    >
        <flux:modal.trigger name="export-card-inventory">
            <flux:button
                icon="arrow-down-tray"
                size="sm"
                class="font-secondary shrink-0 w-full sm:w-auto justify-center !bg-black !text-white !border-0 hover:!bg-neutral-800 dark:!bg-white dark:!text-black dark:hover:!bg-neutral-200"
            >
                Export Inventory
            </flux:button>
        </flux:modal.trigger>
    </x-page-header>

    {{-- Desktop-only action row, matching the Live Queue page's pattern --}}
    <div class="hidden sm:flex sm:items-center sm:justify-between gap-3 mb-6">
        <x-heading
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-section-heading)"
        >
            Manage registered cards and reports
        </x-heading>

        <div class="flex items-center justify-end gap-2 w-full sm:w-auto">
            <flux:link href="{{ route('admin.cards.issue') }}" wire:navigate class="w-full sm:w-auto">
                <flux:button variant="primary" icon="plus" size="sm" class="font-secondary w-full sm:w-auto justify-center bg-primary text-white hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover">
                    Issue New Card
                </flux:button>
            </flux:link>

            <flux:link href="{{ route('admin.cards.reports') }}" wire:navigate class="w-full sm:w-auto">
                <flux:button variant="primary" icon="exclamation-triangle" size="sm" class="font-secondary w-full sm:w-auto justify-center bg-primary text-white hover:bg-primary-hover dark:bg-secondary dark:text-[var(--color-dark-primary)] dark:hover:bg-secondary-hover">
                    Card Reports
                </flux:button>
            </flux:link>
        </div>
    </div>

    {{-- Mobile-visible heading, since the page header above is desktop-only --}}
    <div class="sm:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Cards
            </x-heading>
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
                href="{{ route('admin.cards.issue') }}"
                wire:navigate
                class="font-secondary shrink-0"
            >
                Issue New Card
            </flux:button>

            <flux:button
                variant="primary"
                icon="exclamation-triangle"
                href="{{ route('admin.cards.reports') }}"
                wire:navigate
                class="font-secondary shrink-0"
            >
                Card Reports
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

            <div class="flex gap-2">
                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Paper size</flux:label>
                    <flux:select wire:model.live="exportPaper" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="letter">Letter</flux:select.option>
                        <flux:select.option value="legal">Legal</flux:select.option>
                        <flux:select.option value="a4">A4</flux:select.option>
                    </flux:select>
                </flux:field>

                <flux:field class="flex-1">
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Orientation</flux:label>
                    <flux:select wire:model.live="exportOrientation" size="sm" class="font-secondary text-table-row">
                        <flux:select.option value="portrait">Portrait</flux:select.option>
                        <flux:select.option value="landscape">Landscape</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('export-card-inventory').close(); Flux.modal('preview-card-inventory').show()"
                    icon="eye"
                    variant="primary"
                    class="font-secondary w-full sm:w-auto justify-center"
                >
                    Preview
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- ===================== PREVIEW MODAL ===================== --}}
    <flux:modal
        name="preview-card-inventory"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-3xl mx-auto rounded-xl overflow-hidden"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-4">
            <div class="flex items-start justify-between">
                <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Preview
                </flux:heading>
                <button
                    type="button"
                    x-on:click="Flux.modal('preview-card-inventory').close()"
                    class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1"
                >
                    <flux:icon name="x-mark" class="w-5 h-5" />
                </button>
            </div>

            <iframe
                wire:key="{{ $this->exportPreviewUrl }}"
                src="{{ $this->exportPreviewUrl }}"
                class="w-full h-[60vh] rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-white"
            ></iframe>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:button
                    type="button"
                    x-on:click="Flux.modal('preview-card-inventory').close(); Flux.modal('export-card-inventory').show()"
                    variant="ghost"
                    class="w-full sm:w-auto justify-center font-secondary"
                >
                    Back to filters
                </flux:button>
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

    {{-- ====== SEARCH & FILTERS (inline) ====== --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        {{-- Search --}}
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search name, ID, card…"
                class="w-full font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
                icon="magnifying-glass"
            />
        </div>

        {{-- Filters + Sort + Clear --}}
        <div class="flex flex-wrap sm:flex-nowrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <flux:select
                wire:model.live="statusFilter"
                placeholder="All statuses"
                class="w-full sm:w-40 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="">All statuses</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="suspended">Suspended</flux:select.option>
                <flux:select.option value="terminated">Terminated</flux:select.option>
            </flux:select>

            <flux:select
                wire:model.live="sortBy"
                class="w-full sm:w-44 font-secondary text-table-row dark:bg-dark-secondary dark:border-dark-bd-default dark:text-dark-txt-primary"
            >
                <flux:select.option value="newest">Newest first</flux:select.option>
                <flux:select.option value="oldest">Oldest first</flux:select.option>
                <flux:select.option value="name_asc">Name (A–Z)</flux:select.option>
                <flux:select.option value="name_desc">Name (Z–A)</flux:select.option>
                <flux:select.option value="balance">Highest balance</flux:select.option>
            </flux:select>

            @if ($this->activeFilterCount > 0)
                <flux:button wire:click="clearFilters" size="sm" variant="ghost" icon="x-mark" class="font-secondary shrink-0">
                    Clear
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Cards table – standard card with p-0 and sticky headers --}}
    <flux:card class="mb-4 p-0! overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table container:class="md:max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2 text-center">Name</flux:table.column>
                    <flux:table.column align="center" class="hidden sm:table-cell px-1 sm:px-2 md:px-4 py-2 text-center">Owner ID</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2 text-center">Balance</flux:table.column>
                    <flux:table.column align="center" class="px-1 sm:px-2 md:px-4 py-2 text-center">Status</flux:table.column>
                    <flux:table.column align="center" class="px-1! sm:px-2! md:px-4! py-2 text-center">Details</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getUsers as $index => $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center justify-center gap-2">
                                    <flux:avatar size="xs" src="{{ $user->avatar_url }}" name="{{ $user->name }}" />
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-body">{{ $user->name }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden sm:table-cell px-1 sm:px-2 md:px-4 py-1.5 md:py-2 text-center font-secondary text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->user_code }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 text-center font-secondary text-xs md:text-table-row tabular-nums font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                ₱{{ number_format($user->card->balance, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1 sm:px-2 md:px-4 py-1.5 md:py-2 text-center">
                                @if ($user->card->status === 'active')
                                    <flux:badge color="green" size="sm" class="font-secondary text-badge text-xs">Active</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" class="font-secondary text-badge text-xs">{{ ucfirst($user->card->status) }}</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-1! sm:px-2! md:px-4! py-1.5 md:py-2 text-center">
                                <flux:link href="/admin/card/transaction/{{ $user->id }}" wire:navigate>
                                    <flux:button variant="ghost" size="sm" class="font-secondary text-xs md:text-table-row">
                                        View
                                    </flux:button>
                                </flux:link>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="px-2 md:px-4 py-4">
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

</div>