<flux:card>

      <flux:text class="mt-2 mb-1">
        {{ $title }}
      </flux:text>

      <flux:heading size="xl">{{ $slot }}</flux:heading>

      <flux:text size="sm" class="mt-2 mb-1">
        {{ $description }}
      </flux:text>

  </flux:card>