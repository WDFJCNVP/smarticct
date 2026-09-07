<?php

use Livewire\Component;

new class extends Component
{
    public $selectedLog;

};
?>
<div>
    @if ($this->selectedLog)
        <div class="p-5 space-y-4 font-secondary">

            
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-warning/15 dark:bg-dark-warning/20 flex items-center justify-center shrink-0">
                    <span class="text-xs font-medium text-warning dark:text-dark-warning">
                        {{ collect(explode(' ', $this->selectedLog->user?->name ?? 'U N'))
                            ->filter()
                            ->map(fn($w) => strtoupper(mb_substr($w, 0, 1)))
                            ->implode('') 
                            }}
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-light-txt-primary dark:text-dark-txt-primary truncate">{{ $this->selectedLog->user?->name ?? 'Unknown' }}</p>
                    <p class="text-xs text-light-txt-muted dark:text-dark-txt-muted truncate capitalize">{{ ($this->selectedLog->user?->role ?? '-') }} · {{ $this->selectedLog->user?->username ?? '-' }}</p>
                </div>
            </div>

            <div class="divide-y divide-light-bd-default dark:divide-dark-bd-default rounded-lg border border-light-bd-default dark:border-dark-bd-default overflow-hidden text-sm">
                <div class="flex justify-between items-center gap-3 px-3 py-2">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Date &amp; time</span>
                    <span class="font-mono text-xs text-light-txt-body dark:text-dark-txt-body truncate min-w-0 text-right">{{ $this->selectedLog->created_at->format('M j, Y') }} · {{ $this->selectedLog->created_at->format('g:i A') }}</span>
                </div>
                <div class="flex justify-between items-center gap-3 px-3 py-2">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">Channel</span>
                    <span class="text-light-txt-body dark:text-dark-txt-body truncate min-w-0 text-right">{{ $this->selectedLog->channel }}</span>
                </div>
                <div class="flex justify-between items-center gap-3 px-3 py-2">
                    <span class="text-light-txt-muted dark:text-dark-txt-muted shrink-0">IP address</span>
                    <span class="font-mono text-xs text-light-txt-body dark:text-dark-txt-body truncate min-w-0 text-right">{{ $this->selectedLog->metadata['ip_address'] ?? '-' }}</span>
                </div>
            </div>

            <div>
                <p class="text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted mb-1.5">Subject</p>
                <p class="text-sm text-light-txt-body dark:text-dark-txt-body bg-light-subtle dark:bg-dark-subtle rounded-lg px-3 py-2.5 break-words">{{ $this->selectedLog->subject }}</p>
            </div>

            @if (data_get($this->selectedLog->metadata, 'before') || data_get($this->selectedLog->metadata, 'after'))
                <div>
                    <p class="text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted mb-1.5">State change</p>
                    <div class="rounded-lg border border-light-bd-default dark:border-dark-bd-default overflow-hidden text-xs font-mono divide-y divide-light-bd-default dark:divide-dark-bd-default">
                        @if ($before = data_get($this->selectedLog->metadata, 'before'))
                            <div class="px-3 py-2.5">
                                <p class="text-[10px] text-light-txt-muted dark:text-dark-txt-muted mb-1">Before</p>
                                <p class="text-light-txt-body dark:text-dark-txt-body break-words">{{ $before }}</p>
                            </div>
                        @endif
                        @if ($after = data_get($this->selectedLog->metadata, 'after'))
                            <div class="px-3 py-2.5">
                                <p class="text-[10px] text-light-txt-muted dark:text-dark-txt-muted mb-1">After</p>
                                <p class="text-success dark:text-dark-success break-words">{{ $after }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($message = data_get($this->selectedLog->metadata, 'message'))
                <div>
                    <p class="text-xs font-medium text-light-txt-muted dark:text-dark-txt-muted mb-1.5">Message</p>
                    <p class="text-sm text-light-txt-muted dark:text-dark-txt-muted bg-light-subtle dark:bg-dark-subtle rounded-lg px-3 py-2.5 break-words">
                        {{ $message }}
                    </p>
                </div>
            @endif

        </div>
    @endif
</div>