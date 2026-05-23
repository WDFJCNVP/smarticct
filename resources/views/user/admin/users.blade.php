<x-layouts::dashboard.admin.admin-layout>

<div class="flex mb-5 px-4">
    <flux:heading size="xl" class="flex-1">Users</flux:heading>

    <flux:modal.trigger name="add_user">
        <flux:button size="sm">Add User</flux:button>
    </flux:modal.trigger>

    <flux:modal name="add_user" class="w-full space-y-6" style="max-width: 672px;">
        <div class="space-y-12">
            
            @livewire('pages::content-by-role.admin.register_new_user', key('register-user'))

        </div>
    </flux:modal>
</div>

  <flux:separator />

  <div class="flex items-center justify-start gap-4 mt-8">
    
    <x-panel.admin.total-number-card title="Total Users" description="Number of registered users.">

      <livewire:pages::content-by-role.admin.total_users />

    </x-panel.admin.total-number-card>

    <x-panel.admin.total-number-card title="Commuters" description="Number of registered commuters.">
     <livewire:pages::content-by-role.admin.total_commuter />
    </x-panel.admin.total-number-card>

    <x-panel.admin.total-number-card title="Operators" description="Number of registered operators.">
      <livewire:pages::content-by-role.admin.total_operator />
    </x-panel.admin.total-number-card>

  </div>

  {{-- Table --}}
  <livewire:pages::content-by-role.admin.users/>

</x-layouts::dashboard.admin.admin-layout>