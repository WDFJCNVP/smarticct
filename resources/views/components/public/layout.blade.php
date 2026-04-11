<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>Iriga City Central Terminal</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
  <div class="flex flex-col min-h-screen" x-data="{ open: false }">
    <!-- NAVBAR -->
    <nav class="h-16 bg-white relative z-40">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center">

          <!-- Logo -->
          <div class="flex flex-1 items-center justify-start">
            <a href="/" class="shrink-0">
              <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="logo" class="size-10" />
            </a>
          </div>

          <!-- Desktop Nav Links (center) -->
          <div class="hidden md:flex flex-1 items-center justify-center">
            <div class="flex items-baseline space-x-8">
              <x-public.navlinks href="/" :isActive="request()->is('/')">Explore</x-public.navlinks>
              <x-public.navlinks href="/routes" :isActive="request()->is('routes')">Routes</x-public.navlinks>
              <x-public.navlinks href="/queue" :isActive="request()->is('queue')">Queue</x-public.navlinks>
              <x-public.navlinks href="/fares" :isActive="request()->is('fares')">Fares</x-public.navlinks>
            </div>
          </div>

          <!-- Desktop Right Section -->
          <div class="hidden md:flex flex-1 items-center justify-end space-x-4">
            @guest
              <x-public.navlinks href="/login" :isActive="request()->is('login/*') || request()->is('login')">Login</x-public.navlinks>
            @endguest

            @auth
              <button type="button" class="relative rounded-full p-1 text-gray-400 hover:text-gray-600 focus:outline-none">
                <span class="sr-only">View notifications</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-6">
                  <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>

              <el-dropdown class="relative ml-3">
                <button class="relative flex max-w-xs items-center rounded-full">
                  <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="" class="size-8 rounded-full outline outline-gray-200" />
                </button>
              </el-dropdown>

              <form action="/logout/{{ Auth::user()->role }}" method="post" class="inline">
                @csrf
                <button type="submit" class="text-sm font-semibold text-gray-900 hover:text-[#181E74] cursor-pointer">
                  Logout
                </button>
              </form>
            @endauth
          </div>

          <!-- Mobile Hamburger Button (right side, mobile only) -->
          <div class="flex md:hidden flex-1 justify-end">
            <button
              @click="open = true"
              class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:text-gray-900 focus:outline-none"
              aria-label="Open menu"
            >
              <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
          </div>

        </div>
      </div>
    </nav>

    <!-- Dim Backdrop -->
    <div
      x-show="open"
      x-transition:enter="transition-opacity ease-in-out duration-300"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition-opacity ease-in-out duration-300"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      @click="open = false"
      class="fixed inset-0 z-40 bg-black/40 md:hidden"
      x-cloak
    ></div>

    <!-- Slide-in Panel -->
    <div
      x-show="open"
      x-transition:enter="transition ease-in-out duration-300 transform"
      x-transition:enter-start="-translate-x-full"
      x-transition:enter-end="translate-x-0"
      x-transition:leave="transition ease-in-out duration-300 transform"
      x-transition:leave-start="translate-x-0"
      x-transition:leave-end="-translate-x-full"
      class="fixed top-0 left-0 z-50 h-full w-72 bg-white shadow-xl flex flex-col md:hidden"
      x-cloak
    >
      <!-- Panel Header with Close Button -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <a href="/" class="shrink-0">
          <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="logo" class="size-9" />
        </a>
        <button
          @click="open = false"
          class="rounded-md p-2 text-gray-500 hover:text-gray-800 focus:outline-none"
          aria-label="Close menu"
        >
          <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Nav Links -->
      <nav class="flex flex-col px-4 py-6 space-y-1 flex-grow">
        <x-public.navlinks href="/" :isActive="request()->is('/')">Explore</x-public.navlinks>
        <x-public.navlinks href="/routes" :isActive="request()->is('routes')">Routes</x-public.navlinks>
        <x-public.navlinks href="/queue" :isActive="request()->is('queue')">Queue</x-public.navlinks>
        <x-public.navlinks href="/fares" :isActive="request()->is('fares')">Fares</x-public.navlinks>

        @guest
          <x-public.navlinks href="/login" :isActive="request()->is('login/*') || request()->is('login')">
            Login
          </x-public.navlinks>
        @endguest
      </nav>

      @auth
        <!-- Mobile Logout at Bottom -->
        <div class="px-4 py-4 border-t border-gray-100">
          <form action="/logout/{{ Auth::user()->role }}" method="post">
            @csrf
            <button type="submit" class="w-full text-left text-sm font-semibold text-gray-900 hover:text-[#181E74] cursor-pointer">
              Logout
            </button>
          </form>
        </div>
      @endauth

    </div>
    <main class="w-full flex-grow bg-white">
      {{$slot}}
    </main>
  </div>

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  </body>
</html>
