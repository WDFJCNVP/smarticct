<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;

use App\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

use App\Services\UserService;

new #[Layout('layouts::public-layout')] class extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public string $password_confirmation = '';

    #[Validate('required|string|min:3|max:255')]
    public string $name = '';

    #[Validate('string|min:3|in:commuter')]
    public string $role = 'commuter';

    #[Validate('string|min:3|in:pending')]
    public string $type = 'pending';

    #[Validate('required|string|lowercase|alpha_dash|min:3|max:30|unique:users,username')]
    public string $username = '';

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


    #[Validate('nullable|image|max:5120')]
    public $valid_id;

    // Address modal fields.
    public string $house_subd = '';
    public ?int $zone_number = null;
    public string $municipality = '';
    public string $barangay = '';

    /**
     * Valid ID is only applicable/required for student, senior, and pwd commuter types.
     */
    #[Computed]
    public function requiresValidId(): bool
    {
        return in_array($this->commuter_type, ['student', 'senior', 'pwd'], true);
    }

    public function updatedCommuterType(): void
    {
        // If switching to "regular", clear any uploaded ID and its errors —
        // it's not applicable/required for that type.
        if ($this->commuter_type === 'regular') {
            $this->reset('valid_id');
            $this->resetErrorBag('valid_id');
        }
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

    public function removeValidId(): void
    {
        $this->reset('valid_id');
    }

    public function saveAddress(): void
    {
        $data = $this->validate([
            'house_subd'   => 'nullable|string|max:255',
            'zone_number'  => 'required|integer|min:1|max:20',
            'municipality' => 'required|string|max:255',
            'barangay'     => 'required|string|max:255',
        ]);

        $parts = array_filter([
            $data['house_subd'] !== '' ? $data['house_subd'] : null,
            'Zone ' . $data['zone_number'],
            $data['barangay'],
            $data['municipality'],
            'Camarines Sur',
        ]);

        $this->address = implode(', ', $parts);
        $this->resetValidation();

        $this->dispatch('address-saved');
    }

    public function register()
    {
        // Conditional requirement: valid_id is only mandatory for student/senior/pwd.
        if ($this->requiresValidId && ! $this->valid_id) {
            $this->addError('valid_id', 'Please upload a valid ID for your selected commuter type.');
            return;
        }

        $userBasicInformation = $this->validate();

        $userBasicInformation['valid_id'] = $this->valid_id
            ? $this->valid_id->store('valid-id', 'public')
            : null;

        $user = app(UserService::class)->create($userBasicInformation);

        if ($user) {
            auth()->login($user);
            request()->session()->regenerate();

            return $this->redirect('/' . auth()->user()->role . '/dashboard');
        }
    }
};
?>

{{--
    LAYOUT NOTE: this used to be a two-panel (form | hero image) split, same as
    the login page. For register we want a single centered card instead, with
    a decorative "arch" — the hero photo clipped into a dome shape — peeking
    out from behind the top of the card. The arch is absolutely positioned at
    the top of the page; the card is pushed down with margin-top so only the
    upper portion of the arch is visible above/around it, and the card's own
    (opaque) background naturally covers the rest — that's what reads as
    "behind the form".
--}}

<div
    class="relative min-h-screen w-full overflow-hidden bg-light-primary dark:bg-dark-primary flex flex-col items-center px-6 py-10 sm:py-14"
    x-data="{ loaded: false }"
    x-init="setTimeout(() => loaded = true, 100)"
>

    {{-- ── ARCH BACKDROP ── --}}
    <div class="pointer-events-none absolute inset-x-0 top-0 flex justify-center" aria-hidden="true">
        <div class="relative w-[180%] sm:w-[120%] max-w-4xl h-[300px] sm:h-[360px] rounded-b-[50%] overflow-hidden shadow-lg">
            <img
                src="{{ Vite::asset('resources/images/iriga-terminal.jpg') }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover scale-110"
            >
            <div class="absolute inset-0 bg-linear-to-b from-[#21284D]/85 from-[10%] to-[#272C48]/90 to-[95%]"></div>
        </div>
    </div>

    {{-- ── BRAND MARK, SITTING ON THE ARCH ── --}}
    <a href="/" class="relative z-10 mt-6 sm:mt-10 flex flex-col items-center gap-0.5 text-center">
        <span class="font-primary text-xl font-extrabold text-dark-txt-primary">SMART Iriga City Central Terminal</span>
        <span class="font-secondary text-timestamp text-dark-txt-muted">Rent vehicles, top up your card, and view live queues</span>
    </a>

    {{-- ── FORM CARD ── --}}
    <div
        x-show="loaded"
        x-transition:enter.duration.700
        x-transition:enter.start.opacity-0.translate-y-5
        x-transition:enter.end.opacity-100.translate-y-0
        class="relative z-10 mt-8 sm:mt-10 w-full max-w-sm rounded-2xl bg-light-secondary dark:bg-dark-secondary shadow-xl shadow-black/10 dark:shadow-black/30 px-6 py-8 sm:px-10 sm:py-10"
    >

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
                        wire:model.live="commuter_type"
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
                        <span class="ml-2 text-light-txt-muted dark:text-dark-txt-muted font-normal">(optional)</span>
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

                {{-- Valid ID upload — only applicable to student / senior / pwd commuters. --}}
                @if ($this->requiresValidId)
                    <div class="border-t border-light-bd-default dark:border-dark-bd-default pt-1"></div>

                    <flux:field>
                        <flux:label>
                            Valid ID
                            <x-text>(required for {{ ucfirst($commuter_type === 'pwd' ? 'PWD' : $commuter_type) }})</x-text>
                        </flux:label>

                        @if (! $valid_id)
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
                        @endif

                        <div wire:loading wire:target="valid_id" class="font-secondary text-timestamp text-light-txt-muted dark:text-dark-txt-muted mt-1">
                            Uploading…
                        </div>

                        @if ($valid_id)
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
                        @endif

                        <flux:error name="valid_id" />
                    </flux:field>
                @endif

                <div class="flex gap-2">
                    <flux:button
                        wire:click="prevStep"
                        class="font-primary text-table-row !font-semibold flex-1 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                    >
                        ← Back
                    </flux:button>
                    <flux:button
                        wire:click="sendOtp"
                        wire:loading.attr="disabled"
                        wire:target="sendOtp"
                        class="font-primary hover:bg-secondary! dark:hover:bg-secondary! text-table-row !bg-primary !text-white !font-semibold flex-[3] transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                        variant="filled"
                    >
                        <span wire:loading.remove wire:target="sendOtp">Verify Email →</span>
                        <span wire:loading wire:target="sendOtp">Sending Code...</span>
                    </flux:button>
                </div>

            </div>
        @endif

        @if ($step === 3)
            <div class="flex flex-col gap-6 text-center mt-4">

                <div class="mx-auto rounded-full bg-secondary/10 p-4">
                    <flux:icon name="envelope-open" class="size-10 text-secondary" />
                </div>

                <div>
                    <h3 class="font-primary text-xl font-bold text-light-txt-primary dark:text-dark-txt-primary">Check your email</h3>
                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted mt-2">
                        We've sent a 6-digit verification code to <br>
                        <span class="font-semibold text-secondary">{{ $email_address }}</span>
                    </p>
                </div>

                <flux:field>
                    <flux:input
                        type="text"
                        wire:model="otp"
                        placeholder="000000"
                        maxlength="6"
                        class="text-center font-primary text-3xl tracking-[0.5em] font-bold py-4 bg-light-primary dark:bg-dark-surface border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted/50"
                    />
                    <flux:error name="otp" />
                </flux:field>

                <div class="flex flex-col gap-3 mt-4">
                    <flux:button
                        wire:click="verifyAndRegister"
                        wire:loading.attr="disabled"
                        wire:target="verifyAndRegister"
                        class="font-primary text-lg !bg-primary !text-white !font-semibold w-full py-2 transition-transform duration-200 hover:scale-[1.02] active:scale-[0.97]"
                        variant="filled"
                    >
                        <span wire:loading.remove wire:target="verifyAndRegister">Create Account</span>
                        <span wire:loading wire:target="verifyAndRegister">Verifying...</span>
                    </flux:button>

                    <button wire:click="sendOtp" class="text-sm text-light-txt-muted hover:text-secondary transition-colors">
                        Didn't receive the code? <span class="underline">Resend</span>
                    </button>

                    <button wire:click="prevStep" class="text-xs text-light-txt-muted mt-2 hover:text-secondary transition-colors">
                        ← Change email address
                    </button>
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

    <flux:modal name="address-modal" class="md:w-[26rem]" x-on:address-saved.window="$flux.modal('address-modal').close()">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                    Set your address
                </flux:heading>
                <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    Please provide your complete home address below.
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
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Municipality/City</flux:label>
                <flux:input
                    wire:model="municipality"
                    placeholder="e.g. Iriga City"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
                <flux:error name="municipality" />
            </flux:field>

            <flux:field>
                <flux:label class="font-secondary text-table-row font-medium text-light-txt-body dark:text-dark-txt-primary">Barangay</flux:label>
                <flux:input
                    wire:model="barangay"
                    placeholder="e.g. San Roque"
                    class="font-secondary text-table-row bg-light-primary dark:bg-dark-surface text-light-txt-body dark:text-dark-txt-primary border-light-bd-default dark:border-dark-bd-default placeholder:text-light-txt-muted dark:placeholder:text-dark-txt-muted"
                />
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