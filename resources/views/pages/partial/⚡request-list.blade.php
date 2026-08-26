<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\Post;
use App\Models\RentTransaction;
use App\Models\TripRequest;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;

new class extends Component
{
    public ?Post $post = null;
    public bool $is_show_decline_modal = false;
    public $interested_user = null;
    public bool $is_show_view_more_modal = false;
    public $post_interest_info;

    #[Computed]
    public function getTripRequest()
    {
        return TripRequest::where('post_id', $this->post->id)
            ->whereIn('status', ['pending', 'cancel'])
            ->get();
    }

    #[Computed]
    public function activeTransaction()
    {
        return RentTransaction::where('post_owner_id', $this->post->user_id)
            ->where('status', 'ongoing')
            ->whereHas('tripRequest', function ($query) {
                $query->where('post_id', $this->post->id);
            })
            ->first();
    }

    public function showViewMoreModal($id)
    {
        $this->is_show_view_more_modal = false;
        $this->is_show_view_more_modal = true;
        $this->post_interest_info = TripRequest::where('id', $id)->first();
    }

    public function showDeclineModal($id)
    {
        $this->is_show_decline_modal = false;
        $this->is_show_decline_modal = true;
        $this->interested_user = null;
        $this->interested_user = $this->post->tripRequest->where('id', $id)->first();
    }

    // NOTE: "Accept" no longer opens a modal on this component. It dispatches
    // 'open-confirm-modal', which create_rental_transaction.blade.php listens
    // for and handles with its own modal — same pattern as commuter-request-list.
    // (Previously this component opened its own is_show_confirm_modal and
    // nested create_rental_transaction inside it, but create_rental_transaction
    // renders its own <flux:modal> too, so the accept button ended up stuck
    // inside a second, never-opened modal.)

    public function declineThisInterested_user($id)
    {
        // Guard: if this already ran (e.g. double-click before the modal
        // finished closing), don't run the update/dispatch a second time.
        if (! $this->interested_user) {
            return;
        }

        TripRequest::where('id', $id)->update(['status' => 'decline']);

        // Let the commuter know their trip request was declined.
        $notification = Notification::create([
            'type'    => 'Declined',
            'title'   => 'Trip Request Declined',
            'message' => "Your trip request was declined by {$this->post->user->name}.",
        ]);

        UserNotification::create([
            'notification_id' => $notification->id,
            'user_id'         => $this->interested_user->user_id,
        ]);

        broadcast(new NotificationEvent());

        unset($this->getTripRequest);

        $this->is_show_decline_modal = false;
        $this->interested_user = null;

        $this->dispatch('interested-list-updated');

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Request declined',
            text: 'The trip request has been declined.',
        );
    }

    #[On('transaction-updated')]
    public function refreshRequests()
    {
        unset($this->getTripRequest);
        unset($this->activeTransaction);
    }
};
?>

<div>
    <!-- Request cards (unchanged) – kept as previous version for brevity -->
    @if ($this->activeTransaction)
        <flux:callout
            variant="warning"
            icon="exclamation-circle"
            heading="This vehicle already has an active rental. New requests stay pending until the current transaction ends."
        />

        @forelse ($this->getTripRequest as $request)
            @if ($this->activeTransaction?->trip_request_id === $request->id)
                @continue
            @endif

            <x-card
                class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm my-3 opacity-60"
                disabled
            >
                <div class="flex flex-col sm:flex-row items-start gap-3">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <x-avatar name="{{ $request->user->name }}" color="lime" />
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <x-text variant="strong">{{ $request->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">
                                    Requested {{ $request->created_at->diffForHumans(['short' => true]) }}
                                </x-text>
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-badge color="green" icon="arrows-right-left">
                                    {{ str($request->trip_type)->headline() }}
                                </x-badge>
                                <x-badge color="green" icon="calendar-days">
                                    {{ $request->trip_date->format('D, M j Y') }}
                                </x-badge>
                                <x-badge color="green" icon="users">
                                    {{ $request->body_count }} passengers
                                </x-badge>
                            </div>
                            <div class="mt-1">
                                <x-text>{{ $request->purpose }}</x-text>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0 w-full sm:w-auto">
                        <div class="flex flex-row sm:flex-col items-center gap-2 w-full sm:w-auto">
                            <x-button wire:click="$dispatch('open-confirm-modal', { id: {{ $request->id }} })" variant="primary" color="green" disabled class="w-full sm:w-auto">Accept</x-button>
                            <x-button wire:click="showDeclineModal({{ $request->id }})" variant="primary" color="red" disabled class="w-full sm:w-auto">Decline</x-button>
                        </div>
                        <button
                            type="button"
                            wire:click="showViewMoreModal({{ $request->id }})"
                            class="w-full sm:w-auto text-center text-xs font-medium font-secondary px-3 py-1.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary hover:bg-light-subtle dark:hover:bg-dark-subtle transition cursor-pointer"
                        >
                            View more
                        </button>
                    </div>
                </div>
            </x-card>
        @empty
            {{-- Nothing else pending — the banner above already says everything that's needed here. --}}
        @endforelse
    @else
        @forelse ($this->getTripRequest as $request)
            @if ($this->activeTransaction?->trip_request_id === $request->id)
                @continue
            @endif

            <x-card
                class="!rounded-xl !border !border-light-bd-default dark:!border-dark-bd-default !bg-light-secondary dark:!bg-dark-secondary !shadow-sm my-3"
            >
                @if ($request->status === 'cancel')
                    <flux:badge color="orange" size="sm" class="mb-2">Cancelled</flux:badge>
                @endif
                <div class="flex flex-col sm:flex-row items-start gap-3">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <x-avatar name="{{ $request->user->name }}" color="lime" />
                        <div class="flex flex-col gap-1">
                            <div class="flex flex-wrap items-center gap-x-2">
                                <x-text variant="strong">{{ $request->user->name }}</x-text>
                                <x-text size="sm" variant="subtle">
                                    Requested {{ $request->created_at->diffForHumans(['short' => true]) }}
                                </x-text>
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <x-badge color="green" icon="arrows-right-left">
                                    {{ str($request->trip_type)->headline() }}
                                </x-badge>
                                <x-badge color="green" icon="calendar-days">
                                    {{ $request->trip_date->format('D, M j Y') }}
                                </x-badge>
                                <x-badge color="green" icon="users">
                                    {{ $request->body_count }} passengers
                                </x-badge>
                            </div>
                            <div class="mt-1">
                                <x-text>{{ $request->message }}</x-text>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0 w-full sm:w-auto">
                        <div class="flex flex-row sm:flex-col items-center gap-2 w-full sm:w-auto">
                            <x-button wire:click="$dispatch('open-confirm-modal', { id: {{ $request->id }} })" variant="primary" color="green" class="w-full sm:w-auto">Accept</x-button>
                            <x-button wire:click="showDeclineModal({{ $request->id }})" variant="primary" color="red" class="w-full sm:w-auto">Decline</x-button>
                        </div>
                        <button
                            type="button"
                            wire:click="showViewMoreModal({{ $request->id }})"
                            class="w-full sm:w-auto text-center text-xs font-medium font-secondary px-3 py-1.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary hover:bg-light-subtle dark:hover:bg-dark-subtle transition cursor-pointer"
                        >
                            View more
                        </button>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
                <flux:icon name="clipboard-document-list" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                    No interested commuters yet
                </x-text>
            </x-card>
        @endforelse
    @endif

    <!-- ==================== -->
    <!-- DECLINE MODAL (feed style) -->
    <!-- ==================== -->
    <flux:modal
        wire:model="is_show_decline_modal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Decline this interested user?
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
            @if ($this->interested_user)
                <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                    <flux:modal.close>
                        <x-button type="button" variant="ghost" class="w-full sm:w-auto justify-center !font-secondary">
                            Cancel
                        </x-button>
                    </flux:modal.close>
                    <x-button
                        wire:click="declineThisInterested_user({{ $this->interested_user->id }})"
                        wire:loading.attr="disabled"
                        variant="danger"
                        class="w-full sm:w-auto justify-center !font-secondary"
                    >
                        Decline
                    </x-button>
                </div>
            @endif
        </div>
    </flux:modal>

    <!-- ==================== -->
    <!-- VIEW MORE MODAL (feed style) -->
    <!-- ==================== -->
    <flux:modal
        wire:model="is_show_view_more_modal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <!-- Header -->
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Interested User Details
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Full information about the interested commuter.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <!-- Body -->
            @if ($this->post_interest_info)
                <div class="space-y-5">
                    <!-- Commuter identity -->
                    <div class="flex items-center gap-2.5 rounded-lg border border-light-bd-default dark:border-dark-bd-default p-3">
                        <flux:avatar size="sm" name="{{ $this->post_interest_info->user->name }}" color="emerald" />
                        <div class="flex flex-col">
                            <x-text variant="strong" class="text-sm leading-tight">{{ $this->post_interest_info->user->name }}</x-text>
                            <x-text variant="subtle" style="font-size: var(--text-timestamp)">Commuter</x-text>
                        </div>
                    </div>

                    <!-- Contact details -->
                    <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default divide-y divide-light-bd-default dark:divide-dark-bd-default">
                        <div class="flex items-center justify-between gap-3 p-3">
                            <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                <flux:icon.map-pin class="w-4 h-4" />
                                <x-text class="text-inherit" style="font-size: var(--text-table-row)">Address</x-text>
                            </div>
                            <x-text variant="strong" class="text-right" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->user->address }}</x-text>
                        </div>

                        <div class="flex items-center justify-between gap-3 p-3">
                            <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                <flux:icon.phone class="w-4 h-4" />
                                <x-text class="text-inherit" style="font-size: var(--text-table-row)">Phone no.</x-text>
                            </div>
                            <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->user->phone_number }}</x-text>
                        </div>
                    </div>

                    <!-- User's valid ID -->
                    <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default p-3">
                        @php
                            $url = Storage::url($this->post_interest_info->metadata['valid_ids']['user_valid_id']);
                        @endphp
                        <flux:label class="mb-3">User's valid ID</flux:label>
                        <img src="{{ $url }}" alt="User ID" class="max-w-full rounded border border-light-bd-default dark:border-dark-bd-default" />
                    </div>

                    <!-- Driver details if present -->
                    @if ($this->post_interest_info->metadata['valid_ids']['driver_valid_id'] ?? null)
                        <div>
                            <x-text variant="strong" class="block mb-2" style="font-size: var(--text-table-row)">Driver details</x-text>

                            <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default divide-y divide-light-bd-default dark:divide-dark-bd-default">
                                <div class="flex items-center justify-between gap-3 p-3">
                                    <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                        <flux:icon.user class="w-4 h-4" />
                                        <x-text class="text-inherit" style="font-size: var(--text-table-row)">Name</x-text>
                                    </div>
                                    <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->metadata['driver_name'] }}</x-text>
                                </div>

                                <div class="flex items-center justify-between gap-3 p-3">
                                    <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                        <flux:icon.identification class="w-4 h-4" />
                                        <x-text class="text-inherit" style="font-size: var(--text-table-row)">Age</x-text>
                                    </div>
                                    <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->metadata['driver_age'] }}</x-text>
                                </div>

                                <div class="flex items-center justify-between gap-3 p-3">
                                    <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                        <flux:icon.map-pin class="w-4 h-4" />
                                        <x-text class="text-inherit" style="font-size: var(--text-table-row)">Home address</x-text>
                                    </div>
                                    <x-text variant="strong" class="text-right" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->metadata['driver_home_address'] }}</x-text>
                                </div>

                                <div class="flex items-center justify-between gap-3 p-3">
                                    <div class="flex items-center gap-1.5 text-light-txt-muted dark:text-dark-txt-muted shrink-0">
                                        <flux:icon.phone class="w-4 h-4" />
                                        <x-text class="text-inherit" style="font-size: var(--text-table-row)">Phone no.</x-text>
                                    </div>
                                    <x-text variant="strong" style="font-size: var(--text-table-row)">{{ $this->post_interest_info->metadata['driver_contact_number'] }}</x-text>
                                </div>
                            </div>

                            <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default p-3 mt-3">
                                @php
                                    $driverUrl = Storage::url($this->post_interest_info->metadata['valid_ids']['driver_valid_id']);
                                @endphp
                                <flux:label class="mb-3">Driver's valid ID</flux:label>
                                <img src="{{ $driverUrl }}" alt="Driver ID" class="max-w-full rounded border border-light-bd-default dark:border-dark-bd-default" />
                            </div>
                        </div>
                    @else
                        <flux:badge color="orange" size="sm">User has no driver.</flux:badge>
                    @endif
                </div>
            @endif
        </div>
    </flux:modal>

    {{-- Always mounted so it can catch the 'open-confirm-modal' event dispatched
         by the Accept buttons above, and render its own confirm modal. --}}
    <livewire:pages::partial.create_rental_transaction
        :key="'create-rental-transaction-' . $post->id"
    />
</div>