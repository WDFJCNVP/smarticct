<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
    @fluxAppearance
</head>
<body class="h-full">

    {{-- ── BLURRED BACKGROUND (fixed – covers full viewport) ── --}}
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center filter blur-md scale-110"
             style="background-image: url('{{ asset('images/terminal-bg-4.jpeg') }}')">
        </div>
        <div class="absolute inset-0 bg-black/10 dark:bg-black/60"></div>
    </div>

    {{-- ── PAGE CONTENT ── --}}
    <div class="h-full">
        {{ $slot }}
    </div>

    @livewireScripts
    @fluxScripts
</body>
</html>