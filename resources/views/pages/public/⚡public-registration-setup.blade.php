<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Services\UserService;

new #[Layout('layouts.public-account-setup')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|string|in:regular,student,senior,pwd')]
    public string $commuter_type = '';

    #[Validate('required|integer|between:5,120')]
    public ?int $age = null;

    #[Validate('required|string|regex:/^09\d{9}$/')]
    public string $phone_number = '';

    #[Validate('required|string|max:500')]
    public string $address = '';

    #[Validate('required|string|max:500')]
    public string $name = '';

    #[Validate('accepted')]
    public bool $agree = false;

    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $barangay = '';
    public string $municipality = '';
    public string $province = '';

    public function saveAddress(): void
    {
        $data = $this->validate([
            'house_subd'   => 'nullable|string|max:255',
            'zone_number'  => 'required|integer|min:1|max:20',
            'barangay'     => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'province'     => 'required|string|max:255',
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            $data['municipality'],
            $data['province'],
        ]);

        $this->address = implode(', ', $parts);
        $this->resetValidation();
        $this->dispatch('address-saved');
    }

    public function completeSetup()
    {
        $data = $this->validate([
            'name'          => 'required|string|max:500',
            'commuter_type' => 'required|string|in:regular,student,senior,pwd',
            'age'           => 'required|integer|between:5,120',
            'phone_number'  => 'required|string|regex:/^09\d{9}$/',
            'address'       => 'required|string|max:500',
            'agree'         => 'accepted',
        ]);

        $user = app(UserService::class)->update(auth()->user(), $data, byAdmin: false);

        if ($user) {
            return $this->redirect('/' . auth()->user()->role . '/dashboard');
        }
    }
};
?>

<div class="min-h-screen flex items-center justify-center px-4 py-6 sm:px-6 lg:px-8">

    <div class="w-full max-w-md lg:max-w-4xl rounded-2xl overflow-hidden shadow-md hover:shadow-lg transition-all duration-300 bg-white/90 dark:bg-dark-surface/90 backdrop-blur-sm">

        {{-- Mobile Brand Header (visible only on small screens) --}}
        <div class="block md:hidden px-6 pt-6 pb-0">
            <div class="flex items-center justify-center gap-3">
                <a href="/" class="shrink-0">
                    <img 
                        src="{{ asset('images/logo.png') }}" 
                        alt="SmartICCT" 
                        class="h-10 w-auto"
                    >
                </a>
                <div>
                    <p class="font-primary text-base font-bold text-light-txt-primary dark:text-dark-txt-primary leading-tight">
                        Iriga City
                    </p>
                    <p class="font-secondary text-xs text-light-txt-muted dark:text-dark-txt-muted leading-tight -mt-0.5">
                        Central Terminal
                    </p>
                </div>
            </div>
            <div class="w-full h-0.5 bg-secondary/60 dark:bg-secondary/80 mx-auto mt-3"></div>
        </div>

        {{-- GRADIENT BANNER --}}
        <div class="relative bg-linear-to-r from-primary to-secondary px-6 py-4 sm:px-8 sm:py-4 overflow-hidden">
            <div class="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full border border-white/15"></div>
            <div class="pointer-events-none absolute -right-4 -bottom-12 size-24 rounded-full border border-white/10"></div>

            <div class="relative flex items-center gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white/15">
                    <flux:icon name="user-circle" class="size-5 text-white" />
                </div>
                <div>
                    <p class="font-secondary text-nav-label font-semibold uppercase tracking-widest text-white/70 mb-0.5">
                        Step 2 of 2 · Account Setup
                    </p>
                    <div class="flex gap-1.5 mb-1.5 max-w-[160px]">
                        <div class="flex-1 h-[3px] rounded-full bg-white"></div>
                        <div class="flex-1 h-[3px] rounded-full bg-white"></div>
                    </div>
                    <h2 class="font-primary text-table-row sm:text-page-title font-bold text-white leading-tight">
                        Complete your profile
                    </h2>
                    @auth
                        <p class="font-secondary text-timestamp text-white/80 mt-0.5">
                            Signed in as {{ auth()->user()->email_address }}
                        </p>
                    @endauth
                </div>
            </div>
        </div>

        {{-- FORM BODY --}}
        <div class="p-5 sm:p-6">
            <div class="flex flex-col lg:flex-row gap-5 lg:gap-8 items-stretch">

                <!-- Left column -->
                <div class="flex-1 flex flex-col gap-3">

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Full Name</flux:label>
                        <flux:input
                            type="text"
                            wire:model="name"
                            placeholder="e.g. John Doe"
                            class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="name" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Commuter Type</flux:label>
                        <flux:select
                            wire:model="commuter_type"
                            placeholder="Select commuter type"
                            class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        >
                            <flux:select.option value="regular">Regular</flux:select.option>
                            <flux:select.option value="student">Student</flux:select.option>
                            <flux:select.option value="senior">Senior Citizen</flux:select.option>
                            <flux:select.option value="pwd">PWD</flux:select.option>
                        </flux:select>
                        <flux:error name="commuter_type" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Age</flux:label>
                        <flux:input
                            type="number"
                            wire:model="age"
                            placeholder="e.g. 22"
                            min="5"
                            max="120"
                            class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="age" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                    </flux:field>

                </div>

                <div class="hidden lg:block w-px bg-light-bd-default dark:bg-dark-bd-default self-stretch"></div>

                <!-- Right column -->
                <div class="flex-1 flex flex-col gap-3 justify-between">
                    <div class="flex flex-col gap-3">

                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Phone Number</flux:label>
                            <flux:input
                                type="tel"
                                wire:model="phone_number"
                                placeholder="e.g. 09171234567"
                                class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                            />
                            <flux:error name="phone_number" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                        </flux:field>

                        <flux:field>
                            <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                                Address
                            </flux:label>

                            <button
                                type="button"
                                x-on:click="Flux.modal('address-modal').show()"
                                class="w-full text-left font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 transition-shadow duration-200 focus:outline-none focus:ring-2 focus:ring-secondary/50"
                            >
                                @if ($address)
                                    {{ $address }}
                                @else
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted">Tap to set your address</span>
                                @endif
                            </button>
                            <flux:error name="address" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                        </flux:field>

                        <flux:field class="mt-2">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <flux:checkbox
                                    wire:model="agree"
                                    class="mt-0.5 shrink-0"
                                />
                                <span class="font-secondary text-sm text-light-txt-body dark:text-dark-txt-body leading-snug">
                                    I confirm that all information above is correct and I agree to share it.
                                </span>
                            </label>
                            <flux:error name="agree" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
                        </flux:field>

                    </div>

                    <flux:button
                        wire:click="completeSetup"
                        wire:loading.attr="disabled"
                        wire:target="completeSetup"
                        class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold w-full transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97] mt-1"
                        variant="filled"
                    >
                        <span wire:loading.remove wire:target="completeSetup">Complete Setup →</span>
                        <span wire:loading wire:target="completeSetup">Saving...</span>
                    </flux:button>
                </div>

            </div>
        </div>

    </div>

    {{-- ADDRESS MODAL --}}
    <flux:modal
        name="address-modal"
        :closable="false"
        class="w-[calc(100%-2rem)] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto rounded-xl overflow-hidden"
        x-on:address-saved.window="Flux.modal('address-modal').close()"
    >
        <div class="flex flex-col p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5 overflow-y-auto max-h-[70vh]">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Set your address
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Please provide your complete address.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    House No. / Subdivision
                    <span class="ml-2 text-light-txt-muted dark:text-dark-txt-muted font-normal">(optional)</span>
                </flux:label>
                <flux:input
                    wire:model="house_subd"
                    placeholder="e.g. Blk 3 Lot 5, Hillside Subd."
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="house_subd" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Zone No.</flux:label>
                <flux:input
                    type="number"
                    wire:model="zone_number"
                    min="1"
                    max="20"
                    placeholder="e.g. 3"
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="zone_number" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:input
                    wire:model="barangay"
                    placeholder="e.g. San Roque"
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="barangay" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Municipality / City</flux:label>
                <flux:input
                    wire:model="municipality"
                    placeholder="e.g. Iriga City"
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="municipality" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Province</flux:label>
                <flux:input
                    wire:model="province"
                    placeholder="e.g. Camarines Sur"
                    class="font-secondary text-table-row rounded-lg bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="province" class="font-secondary text-helper text-danger dark:text-dark-danger mt-1" />
            </flux:field>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close class="w-full sm:w-auto">
                    <flux:button type="button" variant="ghost" class="w-full sm:w-auto justify-center font-secondary">
                        Cancel
                    </flux:button>
                </flux:modal.close>
                <flux:button
                    type="button"
                    variant="primary"
                    icon="check"
                    wire:click="saveAddress"
                    wire:loading.attr="disabled"
                    wire:target="saveAddress"
                    class="font-secondary w-full sm:w-auto"
                >
                    Save address
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>