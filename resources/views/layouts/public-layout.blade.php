<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>SMARTICCT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen flex flex-col bg-light-primary dark:bg-dark-primary antialiased">
    <flux:header container class="sticky top-0 z-50 bg-light-secondary dark:bg-dark-secondary border-b border-light-bd-default dark:border-dark-bd-default">
        <flux:sidebar.toggle class="lg:hidden mr-3" icon="bars-2" inset="left" />

        <div class="flex-1 flex items-center gap-2 sm:gap-3 lg:gap-3 md:ml-4 lg:ml-6 min-w-0">
        <a href="/" class="shrink-0">
            <img
                src="{{ asset('images/logo.png') }}"
                alt="SmartICCT"
                class="h-8 w-auto sm:h-9 lg:h-10"
            >
        </a>

    <div class="flex flex-col leading-tight min-w-0">
        <a href="/" class="text-sm sm:text-base font-bold font-primary text-light-txt-primary dark:text-dark-txt-primary lg:text-lg truncate">
            SmartICCT
        </a>
        <span class="hidden sm:block sm:text-xs font-secondary text-light-txt-muted dark:text-dark-txt-muted lg:text-sm whitespace-nowrap">
            Iriga City Central Terminal
        </span>
    </div>
</div>

    <flux:navbar class="max-lg:hidden font-primary text-nav-item font-light absolute left-1/2 -translate-x-1/2">
        <flux:navbar.item href="/" wire:navigate>Explore</flux:navbar.item>
        <flux:navbar.item href="{{ route('route') }}" wire:navigate>Routes</flux:navbar.item>
        <flux:navbar.item href="{{ route('live.queue') }}" wire:navigate>Queue</flux:navbar.item>
        <flux:navbar.item href="{{ route('feed') }}" wire:navigate>Feed</flux:navbar.item>
        <flux:navbar.item href="{{ route('help.center') }}" wire:navigate>Help</flux:navbar.item>
    </flux:navbar>

    <flux:spacer />

    <flux:navbar class="me-4 max-lg:hidden font-secondary text-nav-item font-light gap-2">

        @guest
            @unless(request()->routeIs('login'))
                <flux:navbar.item
                    href="/login"
                    wire:navigate
                    class="!rounded-lg !font-extrabold !border-1 !border-light-bd-default dark:!bg-dark-subtle !text-light-txt-primary dark:!text-dark-txt-primary hover:!bg-light-primary dark:hover:!bg-dark-surface !shadow-none ![box-shadow:none] !border-0 !no-underline"
                >
                    Login
                </flux:navbar.item>
            @endunless

            @unless(request()->routeIs('public.register'))
                <flux:navbar.item
                    href="{{ route('public.register') }}"
                    wire:navigate
                    :current="request()->routeIs('public.register')"
                    class="!rounded-lg !bg-primary !text-white hover:!bg-primary-hover !shadow-none ![box-shadow:none] !border-0 !no-underline"
                >
                    Register
                </flux:navbar.item>
            @endunless
        @endguest

    </flux:navbar>

    </flux:header>

    <flux:sidebar sticky collapsible="mobile" class="lg:hidden bg-light-secondary dark:bg-dark-secondary border-r border-light-bd-default dark:border-dark-bd-default">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="/"
                logo="{{ asset('images/logo.png') }}"
                name="SmartICCT"
            />

            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item href="/" wire:navigate icon="magnifying-glass">Explore</flux:sidebar.item>
            <flux:sidebar.item href="/routes" wire:navigate icon="map-pin">Routes</flux:sidebar.item>
            <flux:sidebar.item href="/queue" wire:navigate icon="queue-list">Queue</flux:sidebar.item>
            <flux:sidebar.item href="/feed" wire:navigate icon="rectangle-stack">Feed</flux:sidebar.item>
            <flux:sidebar.item href="/help" wire:navigate icon="question-mark-circle">Help</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            @guest
                <flux:sidebar.item icon="cog-6-tooth" href="/login">Login</flux:sidebar.item>
                <flux:sidebar.item icon="information-circle" href="/register">Register</flux:sidebar.item>
            @endguest
        </flux:sidebar.nav>
    </flux:sidebar>

        <flux:main class="p-0!">

          {{ $slot }}

        </flux:main>

    @livewireScripts
    @fluxScripts

        @persist('toast')
            <flux:toast position="top end"/>
        @endpersist
</body>
</html>