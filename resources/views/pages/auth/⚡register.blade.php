<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Services\UserService;

new #[Layout('layouts::public-layout')] class extends Component
{
    public int $step = 1;

    public string $password_confirmation = '';

    // Iriga City, Camarines Sur barangays — used both to populate the
    // dropdown and to validate the submitted value against a known list.
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

    #[Validate('required|string|min:3|max:255')]
    public string $name = '';

    #[Validate('string|min:3|in:commuter')]
    public string $role = 'commuter';

    #[Validate('required|string|lowercase|alpha_dash|min:3|max:30|unique:users,username')]
    public string $username = '';

    #[Validate('nullable|string|email|max:255|unique:users,email_address')]
    public string $email_address = '';

    #[Validate('required|string|confirmed')]
    public string $password = '';

    #[Validate('required|string|in:regular,student,senior,pwd')]
    public string $commuter_type = '';

    #[Validate('required|integer|between:5,120')]
    public ?int $age = null;

    #[Validate('required|string|regex:/^09\d{9}$/')]
    public string $phone_number = '';

    #[Validate('required|string|max:500')]
    public string $address = '';

    // Address modal fields.
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $barangay = '';

    #[Computed]
    public function getBarangays()
    {
        return self::BARANGAYS;
    }

    public function nextStep(): void
    {
        $this->validateOnly('name');
        $this->validateOnly('username');
        $this->validateOnly('password');

        $this->step = 2;
    }

    public function prevStep(): void
    {
        $this->step = 1;
    }

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

    public function register()
    {
        $userBasicInformation = $this->validate();

        $user = app(UserService::class)->create($userBasicInformation);

        if ($user) {
            auth()->login($user);
            request()->session()->regenerate();

            return $this->redirect('/' . auth()->user()->role . '/dashboard');
        }
    }
};
?>

<div class="flex h-full overflow-hidden p-10!" x-data="{ loaded: false }" x-init="setTimeout(() => loaded = true, 100)">

    {{-- ── FORM PANEL ── --}}
    <div
        x-show="loaded"
        x-transition:enter.duration.700
        x-transition:enter.start.opacity-0.-translate-x-5
        x-transition:enter.end.opacity-100.translate-x-0
        class="flex flex-1 flex-col justify-center px-6 py-8 sm:px-12 bg-light-secondary dark:bg-dark-secondary overflow-y-auto h-full"
    >
        <div class="w-full max-w-sm mx-auto">

            <p class="font-secondary text-nav-label font-semibold uppercase tracking-widest text-secondary mb-1">Register</p>
            <h2 class="font-primary text-page-title font-bold text-light-txt-primary dark:text-dark-txt-primary mb-1">
                {{ $step === 1 ? 'Create your account' : 'Tell us about yourself' }}
            </h2>
            <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted mb-3">
                {{ $step === 1 ? 'Step 1 of 2 — Account credentials' : 'Step 2 of 2 — Personal details' }}
            </p>

            <div class="flex gap-1.5 mb-5">
                <div class="flex-1 h-[3px] rounded-full bg-secondary"></div>
                <div class="flex-1 h-[3px] rounded-full transition-colors duration-300 {{ $step === 2 ? 'bg-secondary' : 'bg-light-bd-default dark:bg-dark-bd-default' }}"></div>
            </div>

            {{-- ── STEP 1 ── --}}
            @if ($step === 1)
                <div class="flex flex-col gap-4">

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Full Name</flux:label>
                        <flux:input
                            type="text"
                            wire:model="name"
                            placeholder="e.g. Juan dela Cruz"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Username</flux:label>
                        <flux:input
                            type="text"
                            wire:model="username"
                            placeholder="e.g. juandelacruz"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="username" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Password</flux:label>
                        <flux:input
                            type="password"
                            wire:model="password"
                            placeholder="Min. 8 characters"
                            viewable
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Confirm Password</flux:label>
                        <flux:input
                            type="password"
                            wire:model="password_confirmation"
                            placeholder="Re-enter your password"
                            viewable
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="password_confirmation" />
                    </flux:field>

                    <flux:button
                        wire:click="nextStep"
                        class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold w-full transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                        variant="filled"
                    >
                        Continue →
                    </flux:button>

                </div>
            @endif

            {{-- ── STEP 2 ── --}}
            @if ($step === 2)
                <div class="flex flex-col gap-4">

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
                            Email
                            <span class="text-light-txt-muted dark:text-dark-txt-muted font-normal">(optional)</span>
                        </flux:label>
                        <flux:input
                            type="email"
                            wire:model="email_address"
                            placeholder="e.g. juandelacruz@example.com"
                            class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted transition-shadow duration-200 focus:ring-2 focus:ring-secondary/50"
                        />
                        <flux:error name="email_address" />
                    </flux:field>

 
                    <flux:field>
                        <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">
                            Address
                        </flux:label>
                        <flux:modal.trigger name="address-modal">
                            <button
                                type="button"
                                class="w-full text-left font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border border-light-bd-default dark:border-dark-bd-default rounded-lg px-3 py-2.5 transition-shadow duration-200 focus:outline-none focus:ring-2 focus:ring-secondary/50"
                            >
                                @if ($address)
                                    {{ $address }}
                                @else
                                    <span class="text-light-txt-muted dark:text-dark-txt-muted">Tap to set your address</span>
                                @endif
                            </button>
                        </flux:modal.trigger>
                        <flux:error name="address" />
                    </flux:field>

                    <div class="flex gap-2">
                        <flux:button
                            wire:click="prevStep"
                            class="font-primary text-table-row !font-semibold flex-1 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                        >
                            ← Back
                        </flux:button>
                        <flux:button
                            wire:click="register"
                            class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold flex-[3] transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                            variant="filled"
                        >
                            Create account
                        </flux:button>
                    </div>

                </div>
            @endif

            <div class="pt-4">
                <flux:text class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                    Already have an account?
                    <flux:link href="{{ route('login') }}" class="font-secondary text-timestamp text-secondary font-medium">Sign in</flux:link>
                </flux:text>
            </div>

        </div>
    </div>

    {{-- ── HERO PANEL ── --}}
    <div
        x-show="loaded"
        x-transition:enter.duration.700.delay.200
        x-transition:enter.start.opacity-0.translate-x-5
        x-transition:enter.end.opacity-100.translate-x-0
        class="hidden md:flex w-[45%] h-full relative flex-col p-8 overflow-hidden"
    >

        <div class="absolute inset-0 overflow-hidden">
            <a href="/">
                <img
                    src="{{ Vite::asset('resources/images/iriga-terminal.jpg') }}"
                    alt="SmartICCT"
                    class="h-10 w-auto md:h-full scale-105 transition-transform duration-[20s] ease-in-out hover:scale-110"
                >
            </a>
            <div class="absolute inset-0 bg-linear-to-l from-[#21284D]/90 from-[20%] to-[#272C48]/75 to-[75%]"></div>
        </div>

        <div class="relative z-10 flex flex-col justify-start pt-2">
            <h1 class="font-primary text-page-title font-extrabold text-dark-txt-primary leading-tight">
                Welcome to SMART Iriga City Central Terminal.
            </h1>
            <p class="font-secondary text-body text-dark-txt-muted mt-3 leading-relaxed">
                Rent vehicles, top up your card, and view live queues — all from one place.
            </p>
        </div>
    </div>

    <flux:modal name="address-modal" class="md:w-[26rem]" x-on:address-saved.window="$flux.modal('address-modal').close()">
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