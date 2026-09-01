<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>
            Cashier Dashboard
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
    <body class="min-h-screen bg-white dark:bg-dark-secondary">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-light-bd-default bg-light-primary dark:border-dark-bd-default dark:bg-dark-primary">
            <flux:sidebar.header class="flex items-center justify-between gap-3 px-2 pb-2 w-full">
                <a class="flex items-center gap-3 min-w-0" href="{{ route('cashier.dashboard') }}" wire:navigate>
                    <img src="{{ asset('images/logo.png') }}" alt="SmartICCT"
                         class="h-8 w-8 lg:h-14 lg:w-14 shrink-0 object-contain block">
                    <span class="font-primary text-xl lg:text-2xl font-extrabold text-light-txt-primary dark:text-dark-txt-primary tracking-tight truncate flex items-center">
                        SmartICCT
                    </span>
                </a>
                <flux:sidebar.collapse class="lg:hidden shrink-0" />
            </flux:sidebar.header>

            <div class="border-b border-light-bd-default dark:border-dark-bd-default mx-2 mb-1"></div>

            <flux:sidebar.nav class="sidebar-nav-large gap-4 mt-1">
                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('cashier.dashboard') }}" icon="home">
                    Dashboard
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('feed') }}" icon="squares-2x2">
                    Feed
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('route') }}" icon="briefcase">
                    Routes
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('user.queue') }}" icon="truck">
                    Queue
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('admin.travel.record') }}" icon="clipboard-document-list">
                    Dispatch Log
                </x-dashboard.sidebar-menu.sidebar-item>

                <x-dashboard.sidebar-menu.sidebar-item href="{{ route('cashier.cards') }}" icon="credit-card">
                    Card Top-up
                </x-dashboard.sidebar-menu.sidebar-item>
            </flux:sidebar.nav>

            <flux:spacer />

            <livewire:pages::sidebar-profile variant="sidebar" class="hidden lg:block" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header sticky class="lg:hidden border-b border-light-bd-default dark:border-dark-bd-default bg-light-primary dark:bg-dark-primary z-40">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a class="flex items-center gap-2 ml-2 min-w-0" href="{{ route('cashier.dashboard') }}" wire:navigate>
                <img src="{{ asset('images/logo.png') }}" alt="SmartICCT" class="h-7 w-7 shrink-0 object-contain block">
                <span class="font-primary text-base font-extrabold text-light-txt-primary dark:text-dark-txt-primary tracking-tight truncate">
                    SmartICCT
                </span>
            </a>

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