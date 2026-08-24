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
<body class="h-full bg-light-primary dark:bg-dark-primary">

    {{$slot}}
    
    @livewireScripts
    @fluxScripts
</body>
</html>