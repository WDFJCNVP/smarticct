<x-layouts::dashboard.admin.admin-layout>

  <div class="flex mb-5 px-4">
    <flux:heading size="xl" class="flex-1">Users</flux:heading>

    <flux:modal.trigger name="add_user">
      <flux:button size="sm">Add User</flux:button>
    </flux:modal.trigger >

      <flux:modal name="add_user" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Update profile</flux:heading>
                <flux:text class="mt-2">Make changes to your personal details.</flux:text>
            </div>
            <flux:input label="Name" placeholder="Your name" />
            <flux:input label="Date of birth" type="date" />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
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