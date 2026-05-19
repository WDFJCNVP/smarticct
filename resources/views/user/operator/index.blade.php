<x-layouts::dashboard.operator.operator-dashboard>
    <div class="flex gap-4">
        <a href="{{ route('tap.card') }}" aria-label="Latest on our blog" class="flex-1">
            <flux:card size="sm" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                <flux:text class="mt-2">Tap Cards</flux:text>
            </flux:card>
        </a>

        <a href="{{ route('points.option') }}" aria-label="Latest on our blog" class="flex-1">
            <flux:card size="sm" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                <flux:text class="mt-2">Buy Points</flux:text>
            </flux:card>
        </a>
    </div>
</x-layouts::dashboard.operator.operator-dashboard>