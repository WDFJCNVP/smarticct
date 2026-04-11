<x-public.layout>
  <x-public.main-container class="w-full ">
    <div class="w-full min-h-[calc(100vh-64px)] flex flex-col items-center justify-center">
      <div>
        <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="Terminal Logo" class="size-30">
      </div>
      <div class="font-bold pb-5 flex pt-5">Sign in as
        <x-forms.form-header>{{ ucfirst($type) }}</x-forms.form-header>
      </div>
      <div class="flex flex-col justify-center items-center w-1/2 gap-2">

        <x-forms.form action="/login/{{ $type }}" method="POST">
          @csrf

          <x-forms.form-input-container>

            <x-forms.form-label for="email">Username</x-forms.form-label>

            <x-forms.form-input id="email" type="email" name="email" required autocomplete="email"/>

          </x-forms.form-input-container>

          <x-forms.form-input-container>

            <x-forms.form-label for="password">Password</x-forms.form-label>

            <x-forms.form-input id="password" type="password" name="password" required autocomplete="current-password"/>

          </x-forms.form-input-container>

          <x-forms.form-button>Sign in</x-forms.form-button>

        </x-forms.form>

      </div>
      <div class="py-5">
        Not a member?
        <span class="text-primary">
          <a href="#" class="hover:underline">Contact admin</a>
        </span>
      </div>
    </div>
  </x-public.main-container>
</x-public.layout>
