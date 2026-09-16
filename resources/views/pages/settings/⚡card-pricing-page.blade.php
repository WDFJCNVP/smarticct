<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\CardPricingSetting;
use App\Services\AuditLogsService;
use App\Services\BroadcastNotificationService;

new #[Layout('layouts.admin-layout')] class extends Component
{
    #[Validate('required|numeric|min:0')]
    public $price;

    public function mount(): void
    {
        $this->price = CardPricingSetting::currentPrice();
    }

    #[Computed]
    public function setting(): CardPricingSetting
    {
        return CardPricingSetting::current();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $old = CardPricingSetting::currentPrice();

        $setting = CardPricingSetting::current();
        $setting->update([
            'price'      => $validated['price'],
            'updated_by' => auth()->id(),
        ]);

        app(AuditLogsService::class)->create([
            'user_id'  => auth()->id(),
            'action'   => 'Card Price Updated',
            'subject'  => 'Admin updated the card issuance price',
            'channel'  => 'Web',
            'metadata' => [
                'ip_address'  => request()->ip(),
                'old_price'   => $old,
                'new_price'   => $validated['price'],
                'message'     => "Updated card issuance price from ₱{$old} to ₱{$validated['price']}.",
            ],
        ]);

        app(BroadcastNotificationService::class)->notifyRoles(
            roles: ['cashier'],
            type: 'CardPriceChanged',
            title: 'Card Price Updated',
            message: "The card issuance price was updated to ₱{$validated['price']}.",
            metadata: [
                'old_price' => $old,
                'new_price' => $validated['price'],
            ],
        );

        unset($this->setting);

        Flux::toast(
            duration: 4000,
            variant: 'success',
            heading: 'Card price updated',
            text: "New and replacement cards now cost ₱{$validated['price']}.",
        );
    }
};
?>

<div class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Card pricing')" :subheading="__('Set the fee charged when a new or replacement RFID card is issued')">

        <flux:card class="mb-4">
            <x-inputs-container>
                <flux:input type="number" step="0.01" wire:model="price" label="Card issuance price (₱)" placeholder="50.00" />
                <flux:error name="price" />
            </x-inputs-container>
            <flux:button wire:click="save" variant="primary" class="w-full cursor-pointer mt-2">Save</flux:button>
        </flux:card>

        <flux:card class="mt-2">
            <div class="flex items-center justify-between">
                <div>
                    <x-text class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body">Current price</x-text>
                    <x-text class="font-primary text-2xl font-bold tabular-nums block mt-1">₱{{ number_format($this->setting->price, 2) }}</x-text>
                </div>
                @if ($this->setting->updated_by)
                    <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted text-right">
                        Last updated by {{ $this->setting->updatedBy?->name ?? '—' }}<br>
                        {{ $this->setting->updated_at?->diffForHumans() }}
                    </x-text>
                @endif
            </div>
            <x-text class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted block mt-3">
                Applies to every new card issuance (cashier or admin) and every lost/damaged card replacement approved from Card Reports. Charged in cash, collected at the counter before the card is handed over.
            </x-text>
        </flux:card>

    </x-pages::settings.layout>
</div>
