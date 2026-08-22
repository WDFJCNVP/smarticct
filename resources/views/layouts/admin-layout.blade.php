<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin</title>
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
    <flux:sidebar 
        sticky 
        collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
    >
        <flux:sidebar.header class="flex items-center justify-between gap-2 px-2 pb-2 w-full">
            <a class="flex items-center gap-2 min-w-0 shrink">
                <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="SmartICCT"
                     class="h-8 w-8 lg:h-14 lg:w-14 shrink-0 object-contain block">
                <span class="font-primary text-base sm:text-xl lg:text-2xl font-extrabold text-light-txt-primary dark:text-dark-txt-primary tracking-tight whitespace-nowrap flex items-center">
                    SmartICCT
                </span>
            </a>
            <flux:sidebar.collapse class="lg:hidden shrink-0" />
        </flux:sidebar.header>

        <div class="border-b border-zinc-200 dark:border-zinc-700 mx-2 mb-1"></div>

        <flux:sidebar.nav class="sidebar-nav-large gap-4 mt-1">
            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('admin.dashboard') }}"
                icon="home"
            >Dashboard</x-dashboard.sidebar-menu.sidebar-item>

            <livewire:pages::feed-sidebar-item />
            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('admin.routes') }}"
                icon="map"
            >Routes</x-dashboard.sidebar-menu.sidebar-item>

            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('live.queue') }}"
                icon="clock"
                wire:navigate
            >Queueing</x-dashboard.sidebar-menu.sidebar-item>

            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('admin.travel.record') }}"
                icon="briefcase"
            >Travel Records</x-dashboard.sidebar-menu.sidebar-item>

            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('admin.audit.logs') }}"
                icon="shield-check"
            >Audit Logs</x-dashboard.sidebar-menu.sidebar-item>

            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('admin.users') }}"
                icon="users"
                wire:navigate
            >Users</x-dashboard.sidebar-menu.sidebar-item>

            <x-dashboard.sidebar-menu.sidebar-item
                href="{{ route('admin.cards') }}"
                icon="credit-card"
            >Card</x-dashboard.sidebar-menu.sidebar-item>
        </flux:sidebar.nav>

        <flux:spacer />
        <livewire:pages::sidebar-profile />
    </flux:sidebar>

    {{-- Mobile header --}}
    <flux:header class="lg:hidden border-b border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('notifications')" icon="bell" wire:navigate>
                        Notifications
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        Settings
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                                    class="w-full cursor-pointer" data-test="logout-button">
                        Log out
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main>{{ $slot }}</flux:main>

    @fluxScripts
    @livewireScripts
    @persist('toast')<flux:toast position="top end"/>@endpersist
</body>
</html>