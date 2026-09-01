<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.public-layout')] class extends Component
{
    //
};
?>

<div>
    <div class="max-w-6xl mx-auto p-10!">
        <div class="bg-light-secondary dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default rounded-xl p-8">
            <x-pages-heading heading="Help Center" />

            <p class="font-secondary text-body text-light-txt-muted dark:text-dark-txt-muted mt-2">
                Everything you need to know about the Iriga City Central Terminal system — from registration to managing your SmartICCT card.
            </p>
        </div>

        <div class="max-w-6xl mx-auto mt-10">
            <h2 class="font-primary text-sm font-semibold uppercase tracking-wide text-secondary mb-3">
                Who is this for?
            </h2>
            <flux:separator class="mb-5" />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div class="bg-light-secondary dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default rounded-xl p-5">
                    <flux:icon name="user" class="text-info dark:text-dark-info mb-3 size-6" />
                    <h3 class="font-primary font-semibold text-light-txt-primary dark:text-dark-txt-primary mb-1">Commuters</h3>
                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Check trip schedules, view live queues, manage your SmartICCT card, and avail of fare discounts.
                    </p>
                </div>

                <div class="bg-light-secondary dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default rounded-xl p-5">
                    <flux:icon name="truck" class="text-info dark:text-dark-info mb-3 size-6" />
                    <h3 class="font-primary font-semibold text-light-txt-primary dark:text-dark-txt-primary mb-1">Operators</h3>
                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Manage vehicle queuing, ticket fee payments, vehicle registration, and rental postings.
                    </p>
                </div>

                <div class="bg-light-secondary dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default rounded-xl p-5">
                    <flux:icon name="shield-check" class="text-info dark:text-dark-info mb-3 size-6" />
                    <h3 class="font-primary font-semibold text-light-txt-primary dark:text-dark-txt-primary mb-1">ICCT Admin</h3>
                    <p class="font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Automate daily operations, queue reports, user management, and terminal administration.
                    </p>
                </div>

            </div>

            <div class="mt-10 space-y-8">

                {{-- ── Guest Access ── --}}
                <div>
                    <h2 class="font-primary text-sm font-semibold uppercase tracking-wide text-secondary mb-3 flex items-center gap-2">
                        <flux:icon name="eye" class="size-4" />
                        Guest Access
                    </h2>

                    <div x-data="{ open: null }" class="space-y-2">

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 0 ? null : 0"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                Can I use the system without an account?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 0 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 0" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p class="mb-2">Yes. Two pages are publicly accessible at smarticct.com — no registration required:</p>
                                <ul class="space-y-1 list-disc list-inside mb-2">
                                    <li><strong>Routes page</strong> — view trip schedules and fare information</li>
                                    <li><strong>Queue page</strong> — view live vehicle queues at the terminal</li>
                                </ul>
                                <p class="italic text-light-txt-muted dark:text-dark-txt-muted">
                                    All other system features (SmartICCT card, payments, travel history, discounts) require a registered account.
                                </p>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 1 ? null : 1"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                What is the difference between a guest and a registered commuter?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 1 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 1" x-collapse class="px-4 py-4 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm border-collapse">
                                        <thead>
                                            <tr class="border-b border-light-bd-default dark:border-dark-bd-default">
                                                <th class="text-left py-2 pr-4 font-semibold text-light-txt-body dark:text-dark-txt-primary">Feature</th>
                                                <th class="text-center py-2 px-4 font-semibold text-light-txt-body dark:text-dark-txt-primary">Guest User</th>
                                                <th class="text-center py-2 px-4 font-semibold text-light-txt-body dark:text-dark-txt-primary">Registered Commuter</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-b border-light-bd-default dark:border-dark-bd-default">
                                                <td class="py-2 pr-4">View trip schedules &amp; fares</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                            </tr>
                                            <tr class="border-b border-light-bd-default dark:border-dark-bd-default">
                                                <td class="py-2 pr-4">View live queues</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                            </tr>
                                            <tr class="border-b border-light-bd-default dark:border-dark-bd-default">
                                                <td class="py-2 pr-4">Manage travel history</td>
                                                <td class="text-center py-2 px-4">–</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                            </tr>
                                            <tr class="border-b border-light-bd-default dark:border-dark-bd-default">
                                                <td class="py-2 pr-4">SmartICCT card &amp; payments</td>
                                                <td class="text-center py-2 px-4">–</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                            </tr>
                                            <tr>
                                                <td class="py-2 pr-4">Personal transaction management</td>
                                                <td class="text-center py-2 px-4">–</td>
                                                <td class="text-center py-2 px-4 text-success dark:text-dark-success">✓</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 2 ? null : 2"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                I am not from Iriga City. Can I still use the system?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 2 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 2" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p class="mb-2">Yes, partially. If you are not an Iriga City resident, you can access the Routes and Queue pages at smarticct.com as a guest to view trip schedules, fares, and live queue status — with no account needed.</p>
                                <p>However, full system features such as the SmartICCT card, payments, travel history, and discounts are only available to registered users.</p>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── Commuter Registration ── --}}
                <div>
                    <h2 class="font-primary text-sm font-semibold uppercase tracking-wide text-secondary mb-3 flex items-center gap-2">
                        <flux:icon name="user" class="size-4" />
                        Commuter Registration
                    </h2>

                    <div x-data="{ open: null }" class="space-y-2">

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 0 ? null : 0"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                How do I register as a commuter?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 0 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 0" x-collapse class="px-4 py-4 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <ol class="space-y-3">
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">1</span>
                                        <span>Go to smarticct.com and navigate to the Register page.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">2</span>
                                        <span>Fill in your email address and password. This will be used for verification and forgot password feature.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">3</span>
                                        <span>Check your email address for OTP and submit in the form.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">4</span>
                                        <span>Fill in your personal details.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">5</span>
                                        <span>Submit your registration — your account is now active and ready to use!</span>
                                    </li>
                                </ol>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 2 ? null : 2"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                Who can register on the system?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 2 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 2" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p>Full registration is open to Iriga City residents and nearby cities/municipalities.</p>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── SmartICCT Card ── --}}
                <div>
                    <h2 class="font-primary text-sm font-semibold uppercase tracking-wide text-secondary mb-3 flex items-center gap-2">
                        <flux:icon name="credit-card" class="size-4" />
                        SmartICCT Card
                    </h2>

                    <div x-data="{ open: null }" class="space-y-2">

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 0 ? null : 0"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                How do I get a SmartICCT card?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 0 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 0" x-collapse class="px-4 py-4 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">

                                <ol class="space-y-3">
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">1</span>
                                        <span>Create an account at <strong>smarticct.com</strong> (if you don't have one yet).</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">2</span>
                                        <span>Visit the <strong>cashier outlet</strong> at the Iriga City Central Terminal (beside the Office of the ICCT, San Miguel, Iriga City).</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">3</span>
                                        <span>Tell the cashier you'd like to avail a SmartICCT card and show proof of your existing account (e.g. a screenshot).</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">4</span>
                                        <span>Provide your contact number — it will be imprinted on the card for security and recovery purposes.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">5</span>
                                        <span>Pay <strong>₱100</strong> for the card. It comes preloaded with <strong>₱50 worth of points</strong>.</span>
                                    </li>
                                </ol>

                                <div class="mt-4 flex items-start gap-2 bg-info/5 dark:bg-dark-info/10 border border-info/20 dark:border-dark-info/30 rounded-lg p-3">
                                    <flux:icon name="information-circle" class="size-4 text-info dark:text-dark-info shrink-0 mt-0.5" />
                                    <p class="text-info dark:text-dark-info">
                                        Your points can be used for queue fee payments (for operators) and fare payments (for commuters).
                                    </p>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── Operator Registration ── --}}
                <div>
                    <h2 class="font-primary text-sm font-semibold uppercase tracking-wide text-secondary mb-3 flex items-center gap-2">
                        <flux:icon name="truck" class="size-4" />
                        Operator Registration
                    </h2>

                    <div x-data="{ open: null }" class="space-y-2">

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 0 ? null : 0"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                How do I register as an operator?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 0 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 0" x-collapse class="px-4 py-4 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p class="mb-2 font-medium text-light-txt-body dark:text-dark-txt-primary">Prepare the following requirements before visiting the terminal:</p>
                                <ul class="space-y-1 list-disc list-inside mb-3">
                                    <li><strong>Franchise</strong> — issued by the Land Transportation Franchising and Regulatory Board (LTFRB)</li>
                                    <li><strong>OR/CR</strong> — Official Receipt and Certificate of Registration of your vehicle</li>
                                    <li><strong>Expiry date</strong> — of your franchise/OR/CR (critical for renewal tracking)</li>
                                </ul>

                                <p class="mb-2 font-medium text-light-txt-body dark:text-dark-txt-primary">Once your documents are ready, follow these steps:</p>
                                <ol class="space-y-3 mb-3">
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">1</span>
                                        <span>Submit your requirements in person at the Office of the ICCT, San Miguel, Iriga City Central Terminal, Iriga City.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">2</span>
                                        <span>The admin will review your application and issue your system login credentials.</span>
                                    </li>
                                    <li class="flex gap-3">
                                        <span class="shrink-0 size-5 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center">3</span>
                                        <span>Go to smarticct.com, click Login, and enter your credentials to access your operator account.</span>
                                    </li>
                                </ol>

                                <div class="flex items-start gap-2 bg-info/5 dark:bg-dark-info/10 border border-info/20 dark:border-dark-info/30 rounded-lg p-3">
                                    <flux:icon name="information-circle" class="size-4 text-info dark:text-dark-info shrink-0 mt-0.5" />
                                    <p class="text-info dark:text-dark-info">
                                        Important: Your login credentials are issued only once by the ICCT admin. Do not lose or forget them — store them in a secure location. If you need to register an additional vehicle, you must go through the admin again.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 1 ? null : 1"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                What can operators do on the system?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 1 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 1" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p class="mb-2">The SmartICCT system automates the following processes for operators:</p>
                                <ul class="space-y-1 list-disc list-inside">
                                    <li>Vehicle queue management (joining the queue after paying the fee)</li>
                                    <li>Ticket fee payments (via cash or SmartICCT card)</li>
                                    <li>Posting vehicle availability for rent and responding to commuter requests</li>
                                    <li>Viewing trip schedules and queue fee rates</li>
                                    <li>Managing travel records/logbook</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── General Questions ── --}}
                <div>
                    <h2 class="font-primary text-sm font-semibold uppercase tracking-wide text-secondary mb-3 flex items-center gap-2">
                        <flux:icon name="question-mark-circle" class="size-4" />
                        General Questions
                    </h2>

                    <div x-data="{ open: null }" class="space-y-2">

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 0 ? null : 0"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                What is the SmartICCT system?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 0 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 0" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p>SmartICCT is a digital management system for the Iriga City Central Terminal. It was developed to address high-priority issues faced by stakeholders — commuters, operators, and terminal administrators — by automating and digitizing previously manual processes such as ticket payments, queue management, fare inquiries, and travel record management.</p>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 1 ? null : 1"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                Where is the Iriga City Central Terminal located?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 1 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 1" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p>The Iriga City Central Terminal is located at San Miguel, Iriga City. The Office of the ICCT and the SmartICCT card cashier outlet are both within the terminal premises.</p>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 2 ? null : 2"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                I forgot my login credentials. What should I do?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 2 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 2" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p>If you are a commuter, use the account recovery option on the Login page at smarticct.com. If you are an operator and have lost your admin-issued credentials, visit the Office of the ICCT at the terminal for assistance.</p>
                            </div>
                        </div>

                        <div class="border border-light-bd-default dark:border-dark-bd-default rounded-lg overflow-hidden">
                            <button
                                @click="open = open === 3 ? null : 3"
                                class="w-full flex items-center justify-between px-4 py-3 text-left font-secondary text-sm font-medium text-light-txt-body dark:text-dark-txt-primary bg-light-primary dark:bg-dark-surface"
                            >
                                Are Manila‑bound buses covered by SmartICCT?
                                <flux:icon name="chevron-down" class="size-4 transition-transform duration-200" x-bind:class="open === 3 && 'rotate-180'" />
                            </button>

                            <div x-show="open === 3" x-collapse class="px-4 py-3 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-secondary dark:bg-dark-secondary">
                                <p>No. Manila‑bound buses are not managed within SmartICCT. They operate on their own separate system. You can still view their schedule and fare information via the Routes page, but queueing and payments for these buses are handled externally.</p>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ── FOOTER ── --}}
    <footer class="bg-light-secondary dark:bg-dark-secondary border-t border-light-bd-default dark:border-dark-bd-default mt-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-12">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <img src="{{ asset('images/logo.png') }}" alt="SmartICCT" class="h-7 w-auto">
                        <span class="font-primary text-lg font-bold !text-light-txt-primary dark:!text-dark-txt-primary">
                            SmartICCT
                        </span>
                    </div>
                    <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted !leading-relaxed !max-w-xs">
                        Iriga City Central Terminal's digital ecosystem. Real-time queueing, reloadable cards, and seamless travel.
                    </flux:text>
                </div>

                {{-- Quick Links --}}
                <div>
                    <flux:heading size="xs" class="!font-semibold !uppercase !tracking-wide !mb-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                        Quick Links
                    </flux:heading>
                    <nav class="flex flex-col gap-2.5">
                        <flux:link href="/" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Explore
                        </flux:link>
                        <flux:link href="{{ route('live.queue') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Queue
                        </flux:link>
                        <flux:link href="{{ route('route') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Routes
                        </flux:link>
                    </nav>
                </div>

                {{-- Contact & Social --}}
                <div>
                    <flux:heading size="xs" class="!font-semibold !uppercase !tracking-wide !mb-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                        Contact
                    </flux:heading>

                    <div class="flex flex-col gap-2.5 mb-5">
                        <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                            San Nicolas, Iriga City, Camarines Sur
                        </flux:text>
                        <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                            +63 (54) 456-7890
                        </flux:text>
                    </div>

                    {{-- Social icons – inline SVGs with ! on sizing/colors --}}
                    <div class="flex items-center gap-3">
                        <a href="#" aria-label="Facebook"
                        class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                <path d="M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram"
                        class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                <path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.21.6 1.76 1.15.55.55.9 1.1 1.15 1.76.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43-.26.66-.6 1.21-1.15 1.76a4.9 4.9 0 0 1-1.76 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.76-1.15 4.9 4.9 0 0 1-1.15-1.76c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.21 1.15-1.76A4.9 4.9 0 0 1 5.44 2.53c.64-.25 1.37-.42 2.43-.47C8.94 2.01 9.28 2 12 2zm0 1.8c-2.67 0-2.99.01-4.04.06-.97.04-1.5.2-1.85.34-.46.18-.79.4-1.14.74-.34.35-.56.68-.74 1.14-.14.35-.3.88-.34 1.85-.05 1.05-.06 1.37-.06 4.04s.01 2.99.06 4.04c.04.97.2 1.5.34 1.85.18.46.4.79.74 1.14.35.34.68.56 1.14.74.35.14.88.3 1.85.34 1.05.05 1.37.06 4.04.06s2.99-.01 4.04-.06c.97-.04 1.5-.2 1.85-.34.46-.18.79-.4 1.14-.74.34-.35.56-.68.74-1.14.14-.35.3-.88.34-1.85.05-1.05.06-1.37.06-4.04s-.01-2.99-.06-4.04c-.04-.97-.2-1.5-.34-1.85a3.08 3.08 0 0 0-.74-1.14 3.08 3.08 0 0 0-1.14-.74c-.35-.14-.88-.3-1.85-.34-1.05-.05-1.37-.06-4.04-.06zm0 4.59a4.61 4.61 0 1 1 0 9.22 4.61 4.61 0 0 1 0-9.22zm0 1.8a2.81 2.81 0 1 0 0 5.62 2.81 2.81 0 0 0 0-5.62zm5.88-1.99a1.08 1.08 0 1 1-2.16 0 1.08 1.08 0 0 1 2.16 0z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Messenger"
                        class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                <path d="M12 2C6.48 2 2 6.15 2 11.27c0 2.91 1.45 5.5 3.72 7.21V22l3.4-1.87c.91.25 1.87.39 2.88.39 5.52 0 10-4.15 10-9.27S17.52 2 12 2zm1.01 12.49-2.55-2.72-4.97 2.72 5.47-5.8 2.61 2.72 4.91-2.72-5.47 5.8z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <flux:separator class="!my-8 dark:!border-dark-bd-default" />

            <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted !flex !items-center !gap-1.5">
                <flux:icon name="globe-alt" class="!size-3.5" />
                2026 Iriga City Central Terminal. SmartICCT
            </flux:text>
        </div>
    </footer>
</div>