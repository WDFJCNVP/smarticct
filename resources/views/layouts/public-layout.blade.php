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

        <div class="flex-1 flex items-center gap-3 lg:gap-3 md:ml-4 lg:ml-6">
            <a href="/">
                <img 
                    src="{{ Vite::asset('resources/images/logo.png') }}" 
                    alt="SmartICCT" 
                    class="h-9 w-auto lg:h-10"
                >
            </a>

            <div class="flex flex-col leading-tight">
                <a href="/" class="text-base font-bold font-primary text-light-txt-primary dark:text-dark-txt-primary lg:text-lg">
                    SmartICCT
                </a>
                
                <span class="text-xs font-secondary text-light-txt-muted dark:text-dark-txt-muted lg:text-sm">
                    Iriga City Central Terminal
                </span>
            </div>
        </div>

        <flux:spacer />

        <flux:navbar class="me-4 max-lg:hidden font-primary text-nav-item font-light">
            <flux:navbar.item href="/" wire:navigate>Explore</flux:navbar.item>
            <flux:navbar.item href="{{ route('route') }}" wire:navigate>Routes</flux:navbar.item>
            <flux:navbar.item href=" {{ route('live.queue') }}" wire:navigate>Queue</flux:navbar.item>
            <flux:navbar.item href=" {{ route('help.center') }}" wire:navigate>Help Center</flux:navbar.item>

            <flux:separator vertical variant="subtle" class="my-5"/>

            <flux:navbar.item href="/login" class="dark:active:bg-primary" wire:navigate>Login</flux:navbar.item>
            <flux:navbar.item href="{{ route('public.register') }}" wire:navigate>Register</flux:navbar.item>
        </flux:navbar>

    </flux:header>

    <flux:sidebar sticky collapsible="mobile" class="lg:hidden bg-light-secondary dark:bg-dark-secondary border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.header>
            <flux:sidebar.brand
                href="/"
                logo="{{ Vite::asset('resources/images/logo.png') }}"
                logo:dark="{{ Vite::asset('resources/images/dark-mode-logo.png') }}"
                name="SmartICCT"
            />

            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item href="/" wire:navigate icon="magnifying-glass">Explore</flux:sidebar.item>
            <flux:sidebar.item href="/routes" wire:navigate icon="map-pin">Routes</flux:sidebar.item>
            <flux:sidebar.item href="/queue" wire:navigate icon="queue-list">Queue</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="/login">Login</flux:sidebar.item>
            <flux:sidebar.item icon="information-circle" href="/register">Register</flux:sidebar.item>
        </flux:sidebar.nav>
    </flux:sidebar>

        <flux:main class="!p-8 flex-1 overflow-y-auto">

          {{ $slot }}

        </flux:main>

    @livewireScripts
    @fluxScripts
</body>
</html>