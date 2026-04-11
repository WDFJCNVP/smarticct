<x-public.layout>
  <div class="relative w-full flex-1 flex items-center">

    <x-public.image-overlay/>

    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 min-h-[calc(100vh-64px)] flex flex-col lg:flex-row items-center justify-start gap-10 py-10">
      {{ $slot }}
    </div>

  </div>
</x-public.layout>
