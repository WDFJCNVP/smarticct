<x-layouts::dashboard.admin.admin-layout>

<div class="mb-5 px-4">
  <div>
    <flux:heading size="xl" class="flex-1">User Management</flux:heading>
    <flux:subheading>You can manage and monitor all users here.</flux:subheading>
  </div>
    {{-- <flux:modal.trigger name="add_user">
        <flux:button size="sm">Add Users</flux:button>
    </flux:modal.trigger> --}}

    {{-- <flux:modal name="add_user" class="w-full space-y-6" style="max-width: 672px;">
        <div class="space-y-12">
            
            @livewire('pages::content-by-role.admin.register_new_user', key('register-user'))

        </div>
    </flux:modal> --}}
</div>

  {{-- <div class="flex items-center justify-start gap-4 mt-8">
    
    <x-panel.admin.total-number-card title="Total Users" description="Number of registered users.">
      <livewire:pages::content-by-role.admin.total_users />
    </x-panel.admin.total-number-card>

    <x-panel.admin.total-number-card title="Commuters" description="Number of registered commuters.">
     <livewire:pages::content-by-role.admin.total_commuter />
    </x-panel.admin.total-number-card>

    <x-panel.admin.total-number-card title="Operators" description="Number of registered operators.">
      <livewire:pages::content-by-role.admin.total_operator />
    </x-panel.admin.total-number-card>

  </div> --}}

  <livewire:pages::content-by-role.admin.users/>

</x-layouts::dashboard.admin.admin-layout>