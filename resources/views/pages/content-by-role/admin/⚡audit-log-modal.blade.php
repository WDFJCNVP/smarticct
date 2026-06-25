<?php

use Livewire\Component;

new class extends Component
{
    public $selectedLog;

};
?>
<div>
    @if ($this->selectedLog)
        <div class="p-5 space-y-4">

            
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center shrink-0">
                    <span class="text-xs font-medium text-amber-700 dark:text-amber-300">
                        {{ collect(explode(' ', $this->selectedLog->user?->name ?? 'U N'))
                            ->filter()
                            ->map(fn($w) => strtoupper(mb_substr($w, 0, 1)))
                            ->implode('') 
                            }}
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $this->selectedLog->user?->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-zinc-400 truncate capitalize">{{ ($this->selectedLog->user?->role ?? '-') }} · {{ $this->selectedLog->user?->username ?? '-' }}</p>
                </div>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800 rounded-lg border border-zinc-100 dark:border-zinc-800 overflow-hidden text-sm">
                <div class="flex justify-between px-3 py-2">
                    <span class="text-zinc-500">Date & time</span>
                    <span class="font-mono text-xs">{{ $this->selectedLog->created_at->format('M j, Y') }} · {{ $this->selectedLog->created_at->format('g:i A') }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                    <span class="text-zinc-500">Channel</span>
                    <span>{{ $this->selectedLog->channel }}</span>
                </div>
                <div class="flex justify-between px-3 py-2">
                    <span class="text-zinc-500">IP address</span>
                    <span class="font-mono text-xs">{{ $this->selectedLog->metadata['ip_address'] ?? '-' }}</span>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-zinc-500 mb-1.5">Subject</p>
                <p class="text-sm bg-zinc-50 dark:bg-zinc-800 rounded-lg px-3 py-2.5">{{ $this->selectedLog->subject }}</p>
            </div>

            @if (data_get($this->selectedLog->metadata, 'before') || data_get($this->selectedLog->metadata, 'after'))
                <div>
                    <p class="text-xs font-medium text-zinc-500 mb-1.5">State change</p>
                    <div class="rounded-lg border border-zinc-100 dark:border-zinc-800 overflow-hidden text-xs font-mono divide-y divide-zinc-100 dark:divide-zinc-800">
                        @if ($before = data_get($this->selectedLog->metadata, 'before'))
                            <div class="px-3 py-2.5">
                                <p class="text-[10px] text-zinc-400 mb-1">Before</p>
                                <p class="text-zinc-600 dark:text-zinc-300">{{ $before }}</p>
                            </div>
                        @endif
                        @if ($after = data_get($this->selectedLog->metadata, 'after'))
                            <div class="px-3 py-2.5">
                                <p class="text-[10px] text-zinc-400 mb-1">After</p>
                                <p class="text-green-600 dark:text-green-400">{{ $after }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($message = data_get($this->selectedLog->metadata, 'message'))
                <div>
                    <p class="text-xs font-medium text-zinc-500 mb-1.5">Message</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800 rounded-lg px-3 py-2.5">
                        {{ $message }}
                    </p>
                </div>
            @endif

        </div>
    @endif
</div>