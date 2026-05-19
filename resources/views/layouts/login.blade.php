<x-public.layout>
    <div class="w-full min-h-[calc(100vh-64px)] flex flex-col items-center justify-center px-4">
      
      <div class="mb-4">
        <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="Terminal Logo" class="size-24 object-contain">
      </div>

      <div class="flex flex-col justify-center items-center py-2">

        <div> Welcome to SMART Iriga City </div>

        <div>Central Terminal</div>

      </div>

      <div class="flex flex-col justify-center items-center w-full">

        <div class="w-full max-w-sm">

          {{ $slot }}
          
        </div>

        <div class="py-10 text-sm text-zinc-500 dark:text-zinc-400">

            <flux:text>
              
              Not a member?

              <flux:link href="#"> Register</flux:link>
              
            </flux:text>

        </div>

    </div>
</x-public.layout>