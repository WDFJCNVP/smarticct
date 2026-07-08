<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

use App\Models\User;

new class extends Component
{
    #[Computed]
    public function getPendingUsers() {
        return User::where('type', 'pending')->whereIn('role', ['operator', 'commuter'])->orderBy('created_at', 'desc')->paginate(10);
    }
};
?>

<div>

    <x-pages-heading>Pending Users</x-pages-heading>

    <flux:card>
        <div class="overflow-x-auto">
            <flux:table container:class="max-h-160">
                <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">ID</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Card</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Name</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Username</flux:table.column>
                    <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Address</flux:table.column>
                    <flux:table.column align="center" class="px-2 md:px-4 py-2">Role</flux:table.column>
                    <flux:table.column align="center" class="px-2! md:px-4! py-2">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getPendingUsers as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->user_code }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-mono text-xs md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->card ? '**** **** **** ' . substr($user->card->card_number, -4) : '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                <div class="flex items-center gap-1.5 md:gap-2">
                                    <flux:avatar size="xs" src="{{ $user->avatar_url }}" name="{{ $user->name }}" />
                                    <span class="font-secondary text-xs md:text-table-row text-light-txt-body dark:text-dark-txt-primary">
                                        {{ $user->name }}
                                    </span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                {{ $user->username }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted max-w-48 truncate">
                                {{ $user->address ?: '—' }}
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                @if ($user->role === 'operator')
                                    <flux:badge color="blue" size="sm" class="font-secondary text-badge text-xs">Operator</flux:badge>
                                @else
                                    <flux:badge color="yellow" size="sm" class="font-secondary text-badge text-xs">Commuter</flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2">
                                <flux:link href="/admin/edit/user/{{ $user->id }}" wire:navigate>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" class="scale-75 md:scale-100" />
                                </flux:link>
                            </flux:table.cell>
                        </flux:table.row>

                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="px-2 md:px-4 py-4">
                                <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                    <flux:icon.users class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                    <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                        No users found.
                                    </x-text>
                                    @if ($search)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            Try a different search term.
                                        </x-text>
                                    @elseif ($filtered_role)
                                        <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                            No {{ $filtered_role }}s registered yet.
                                        </x-text>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        @if ($this->getPendingUsers->hasPages())
            <div class="flex flex-wrap items-center justify-end gap-2 px-3 sm:px-4 py-2 border-t border-light-bd-default dark:border-dark-bd-default bg-light-secondary dark:bg-dark-secondary">
                {{ $this->getPendingUsers->links() }}
            </div>
        @endif
    </flux:card>
</div>
