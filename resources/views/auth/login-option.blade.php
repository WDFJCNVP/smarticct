<x-public.layout>
  <x-public.main-container class="w-full ">
    <div class="w-full min-h-[calc(100vh-64px)] flex flex-col items-center justify-center">
      <div>
        <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="Terminal Logo" class="size-30">
      </div>
      <div class="flex flex-col justify-center items-center py-5">
        <div>Welcome to SMART Iriga City </div>
        <div>Central Terminal</div>
      </div>
      <div class="font-bold pb-5">Login as</div>
      <div class="flex flex-col justify-center items-center w-1/2 gap-2">
        <x-forms.form-section-button href="/login/admin" class="w-1/2">Admin</x-forms.form-section-button>
        <x-forms.form-section-button href="/login/operator" class="w-1/2">Operator</x-forms.form-section-button>
        <x-forms.form-section-button href="/login/passenger" class="w-1/2">Passenger</x-forms.form-section-button>
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
