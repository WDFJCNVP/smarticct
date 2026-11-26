<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

use App\Models\User;
use App\Models\Vehicle;

new #[Layout('layouts.admin-layout')] class extends Component
{
    public User $user;

    #[Computed]
    public function vehicleCount(): int
    {
        return Vehicle::where('user_id', $this->user->id)->count();
    }

    /**
     * The child components dispatch "user-updated" after they change anything
     * the page header / banners depend on (account, card, suspension, profile,
     * vehicle count). Re-read the user and the cached vehicle count.
     */
    #[On('user-updated')]
    public function refreshUser(): void
    {
        $this->user->refresh();
        unset($this->vehicleCount);
    }
};
?>

<div>
    <x-page-header description="User Information" class="mb-3" />

    {{-- Breadcrumbs moved to the right, aligned with the heading --}}
    <div class="flex items-center justify-between mb-4">
        <x-heading
            size="xl"
            class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
            style="font-size: var(--text-section-heading)"
        >
            Edit User Information
        </x-heading>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('admin.users') }}" wire:navigate>Back to Users</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->user->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    {{-- No-account / no-card banners --}}
    <livewire:pages::user-info.account-banners :user="$user" :key="'banners-'.$user->id" />

    {{-- Card issuance panel (opened from the banner) --}}
    <livewire:pages::user-info.card-issuance :user="$user" :key="'card-'.$user->id" />

    {{-- Profile header --}}
    <x-user-info.profile-header :user="$user" :vehicle-count="$this->vehicleCount" />

    {{-- Personal information form (+ address modal, email OTP modal, suspend/reinstate) --}}
    <livewire:pages::user-info.personal-form :user="$user" :key="'personal-'.$user->id" />

    <x-user-info.suspension-notice :user="$user" />

    {{-- Operator vehicle section --}}
    @if ($user->role === 'operator')
        <livewire:pages::user-info.vehicles :user="$user" :key="'vehicles-'.$user->id" />
    @endif
</div>