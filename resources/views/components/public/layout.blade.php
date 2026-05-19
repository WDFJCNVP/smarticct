<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>SMARTICCT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
    <flux:header container class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <div class="flex-1 flex items-center">
            <flux:brand
                href="/"
                logo="{{ Vite::asset('resources/images/logo.png') }}"
                name="SmartICCT"
                class="-mb-px max-lg:hidden"
            />
        </div>

        <flux:spacer />

        <flux:navbar class="me-4 max-lg:hidden">
            <flux:navbar.item href="/" wire:navigate>Explore</flux:navbar.item>
            <flux:navbar.item href="/routes" wire:navigate>Routes</flux:navbar.item>
            <flux:navbar.item href="/queue" wire:navigate>Queue</flux:navbar.item>

            <flux:separator vertical variant="subtle" class="my-2"/>

            <flux:navbar.item href="/login" wire:navigate>Login</flux:navbar.item>
            <flux:navbar.item href="/register" wire:navigate>Register</flux:navbar.item>
        </flux:navbar>

    </flux:header>

    <flux:sidebar sticky collapsible="mobile" class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
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
            <flux:sidebar.item href="/" wire:navigate>Explore</flux:sidebar.item>
            <flux:sidebar.item href="/routes" wire:navigate>Routes</flux:sidebar.item>
            <flux:sidebar.item href="/queue" wire:navigate>Queue</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="cog-6-tooth" href="/login">Login</flux:sidebar.item>
            <flux:sidebar.item icon="information-circle" href="/register">Register</flux:sidebar.item>
        </flux:sidebar.nav>
    </flux:sidebar>

    <main class="[grid-area:main] [[data-flux-container]_&]:px-0 mx-auto w-full [:where(&)]:max-w-7xl" data-flux-main>

        {{$slot}}

    </main>
    @livewireScripts
    @fluxScripts
</body>
</html>