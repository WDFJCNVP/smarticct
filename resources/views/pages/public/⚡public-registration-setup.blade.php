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

    public const BARANGAYS = [
        'Antipolo',
        'Cristo Rey',
        'Del Rosario (Banao)',
        'Francia',
        'La Anunciacion',
        'La Medalla',
        'La Purisima',
        'La Trinidad',
        'Niño Jesus',
        'Perpetual Help',
        'Sagrada',
        'Salvacion',
        'San Agustin',
        'San Andres',
        'San Antonio',
        'San Francisco (Pob.)',
        'San Isidro',
        'San Jose',
        'San Juan',
        'San Miguel',
        'San Nicolas',
        'San Pedro',
        'San Rafael',
        'San Ramon',
        'San Roque (Pob.)',
        'Santiago',
        'San Vicente Norte',
        'San Vicente Sur',
        'Santa Cruz Norte',
        'Santa Cruz Sur',
        'Santa Elena',
        'Santa Isabel',
        'Santa Maria',
        'Santa Teresita',
        'Santo Domingo',
        'Santo Niño',
    ];

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

    // #[Validate('required|image|max:5120')]
    // public $valid_id;

    // Address modal fields.
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $barangay = '';

    #[Computed]
    public function getBarangays()
    {
        return self::BARANGAYS;
    }

    // public function removeValidId(): void
    // {
    //     $this->reset('valid_id');
    // }

    public function saveAddress(): void
    {
        $data = $this->validate([
            'house_subd'  => 'nullable|string|max:255',
            'zone_number' => 'required|integer|min:1|max:20',
            'barangay'    => 'required|string|in:' . implode(',', self::BARANGAYS),
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            'Iriga City',
            'Camarines Sur',
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
            // 'valid_id'      => 'required|image|max:5120',
        ]);

        $user = app(UserService::class)->update(auth()->user(), $data);

        if ($user) {
            return $this->redirect('/' . auth()->user()->role . '/dashboard');
        }
    }
};
?>

<div class="min-h-screen flex items-center justify-center px-4 py-8 sm:px-6 lg:px-8 bg-light-primary dark:bg-dark-bg">


    <x-card class="w-full max-w-md lg:max-w-4xl p-6 sm:p-8 bg-white dark:bg-dark-surface rounded-2xl shadow-md transition-all duration-300 hover:shadow-lg">
        
        <div class="flex flex-col lg:flex-row gap-6 lg:gap-10 items-stretch">

            <div class="flex-1 flex flex-col gap-4">
                <div>
                    <p class="font-secondary text-nav-label font-semibold uppercase tracking-widest text-secondary mb-1">Account Setup</p>
                    <h2 class="font-primary text-page-title font-bold text-light-txt-primary dark:text-dark-txt-primary mb-1">
                        Tell us about yourself
                    </h2>
                    <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted">
                        Complete your personal details to finalize your registration.
                    </p>
                </div>

                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Full Name</flux:label>
                    <flux:input
                        type="text"
                        wire:model="name"
                        placeholder="e.g. John Doe"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                    />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Commuter Type</flux:label>
                    <flux:select
                        wire:model="commuter_type"
                        placeholder="Select commuter type"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                    >
                        <flux:select.option value="regular">Regular</flux:select.option>
                        <flux:select.option value="student">Student</flux:select.option>
                        <flux:select.option value="senior">Senior Citizen</flux:select.option>
                        <flux:select.option value="pwd">PWD</flux:select.option>
                    </flux:select>
                    <flux:error name="commuter_type" />
                </flux:field>

                <flux:field>
                    <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Age</flux:label>
                    <flux:input
                        type="number"
                        wire:model="age"
                        placeholder="e.g. 22"
                        min="5"
                        max="120"
                        class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                    />
                    <flux:error name="age" />
                </flux:field>

            </div>

            <div class="hidden lg:block w-px bg-light-bd-default dark:bg-dark-bd-default self-stretch"></div>

            <div class="flex-1 flex flex-col gap-4 justify-between">
                <div class="flex flex-col gap-4">

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Phone Number</flux:label>
                        <flux:input
                            type="tel"
                            wire:model="phone_number"
                            placeholder="e.g. 09171234567"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="phone_number" />
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
                        <flux:error name="address" />
                    </flux:field>

                    <flux:field>
                        {{-- <flux:label>
                            Valid ID
                            <span class="text-xs text-light-txt-muted dark:text-dark-txt-muted">(required)</span>
                        </flux:label> --}}

                        {{-- @if (! $valid_id)
                            <label
                                for="valid_id"
                                class="flex flex-col items-center justify-center gap-1 w-full cursor-pointer rounded-lg border border-dashed border-light-bd-default dark:border-dark-bd-default bg-light-primary dark:bg-dark-surface px-4 py-5 text-center transition-colors duration-200 hover:border-secondary"
                            >
                                <flux:icon name="identification" class="size-6 text-light-txt-muted dark:text-dark-txt-muted" />
                                <span class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                    Upload a photo of your ID
                                </span>
                                <span class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                    JPG or PNG, max 5MB
                                </span>
                                <input
                                    id="valid_id"
                                    type="file"
                                    accept="image/*"
                                    wire:model="valid_id"
                                    class="hidden"
                                />
                            </label>
                        @endif --}}

                        {{-- <div wire:loading wire:target="valid_id" class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted mt-1">
                            Uploading…
                        </div> --}}

                        {{-- @if ($valid_id)
                            <div class="flex items-center gap-3 rounded-lg border border-light-bd-default dark:border-dark-bd-default bg-light-primary dark:bg-dark-surface px-3 py-2">
                                <img
                                    src="{{ $valid_id->temporaryUrl() }}"
                                    alt="Valid ID preview"
                                    class="size-10 rounded-md object-cover shrink-0"
                                />
                                <span class="font-secondary text-table-row text-light-txt-body dark:text-dark-txt-primary truncate flex-1">
                                    {{ $valid_id->getClientOriginalName() }}
                                </span>
                                <flux:icon name="check-circle" class="size-4 text-green-600 shrink-0" />
                                <button
                                    type="button"
                                    wire:click="removeValidId"
                                    class="shrink-0 text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-primary"
                                >
                                    <flux:icon name="x-mark" class="size-4" />
                                </button>
                            </div>
                        @endif --}}

                        {{-- <flux:error name="valid_id" /> --}}
                    </flux:field>
                </div>

                <flux:button
                    wire:click="completeSetup"
                    wire:loading.attr="disabled"
                    wire:target="completeSetup"
                    class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold w-full transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97] mt-2"
                    variant="filled"
                >
                    <span wire:loading.remove wire:target="completeSetup">Complete Setup →</span>
                    <span wire:loading wire:target="completeSetup">Saving...</span>
                </flux:button>
            </div>

        </div>
    </x-card>

    {{-- ── ADDRESS MODAL ── --}}
    <flux:modal name="address-modal" class="md:w-[26rem]" x-on:address-saved.window="Flux.modal('address-modal').close()">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Set your address
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    All addresses are within Iriga City, Camarines Sur.
                </flux:text>
            </div>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                    House No. / Subdivision
                    <span class="ml-2 text-light-txt-muted dark:text-dark-txt-muted font-normal">(optional)</span>
                </flux:label>
                <flux:input
                    wire:model="house_subd"
                    placeholder="e.g. Blk 3 Lot 5, Hillside Subd."
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="house_subd" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Zone No.</flux:label>
                <flux:input
                    type="number"
                    wire:model="zone_number"
                    min="1"
                    max="20"
                    placeholder="e.g. 3"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="zone_number" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:select
                    wire:model="barangay"
                    placeholder="Select barangay"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default"
                >
                    @foreach ($this->getBarangays as $brgy)
                        <flux:select.option value="{{ $brgy }}">{{ $brgy }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="barangay" />
            </flux:field>

            <div class="flex flex-col sm:flex-row justify-end gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost" class="font-secondary w-full sm:w-auto">
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