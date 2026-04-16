@props(['heading'])

<flux:sidebar.group expandable heading="{{ $heading }}" class="grid">
  {{ $slot }}
</flux:sidebar.group>