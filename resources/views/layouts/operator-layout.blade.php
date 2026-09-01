<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
        </title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
        @fluxAppearance

        <style>
            .sidebar-nav-large [data-flux-sidebar-item],
            .sidebar-nav-large a {
                padding-top: 0.875rem !important;
                padding-bottom: 0.875rem !important;
                font-size: 1.125rem !important;
            }
            .sidebar-nav-large span {
                font-size: 1.125rem !important;
            }
            .sidebar-nav-large svg {
                width: 1.5rem !important;
                height: 1.5rem !important;
            }
        </style>
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header class="flex items-center justify-between gap-3 px-2 pb-2 w-full">
                <a class="flex items-center gap-3 min-w-0" href="{{ route('operator.dashboard') }}" wire:navigate>
                    <img src="{{ asset('images/logo.png') }}" alt="SmartICCT"
                         class="h-8 w-8 lg:h-14 lg:w-14 shrink-0 object-contain block">
                    <span class="font-primary text-xl lg:text-2xl font-extrabold text-light-txt-primary dark:text-dark-txt-primary tracking-tight truncate flex items-center">
                        SmartICCT
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden shrink-0" />
            </flux:sidebar.header>

            <div class="border-b border-zinc-200 dark:border-zinc-700 mx-2 mb-1"></div>

            <flux:sidebar.nav class="sidebar-nav-large gap-4 mt-1">
                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('operator.dashboard') }}" icon="home" wire:navigate>
                    Dashboard
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('feed') }}" icon="squares-2x2" wire:navigate>
                    Feed
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="/operator/vehicles" icon="truck" wire:navigate>
                    My Vehicle
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('user.queue') }}" icon="clock" wire:navigate>
                    Queueing
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('operator.transaction') }}" icon="credit-card" wire:navigate>
                    Transactions
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('operator.earnings') }}" icon="credit-card" wire:navigate>
                    Earnings
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('user.card') }}" icon="credit-card" wire:navigate>
                    My Card
                </x-dashboard.sidebar-menu.sidebar-item>
            </flux:sidebar.nav>

            <flux:spacer />

            <livewire:pages::sidebar-profile variant="sidebar" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden border-b border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:pages::sidebar-profile variant="mobile" />
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @fluxScripts
        @livewireScripts

        @persist('toast')
            <flux:toast position="top end"/>
        @endpersist
    </body>
</html>