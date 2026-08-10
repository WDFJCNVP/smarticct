<?php
namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Post;

new class extends Component
{
    public int $unreadCount = 0;

    public function mount()
    {
        $this->calculateUnread();
    }

    public function calculateUnread()
    {
        $user = auth()->user();
        if (! $user) return;

        // If currently on the feed page, mark as read immediately
        if (request()->routeIs('feed')) {
            $user->update(['last_feed_viewed_at' => now()]);
            $this->unreadCount = 0;
            return;
        }

        // Count posts created by other users after last_feed_viewed_at
        $query = Post::where('user_id', '!=', $user->id)
            ->where('status', 'active');

        if ($user->last_feed_viewed_at) {
            $query->where('created_at', '>', $user->last_feed_viewed_at);
        }

        $this->unreadCount = $query->count();
    }

    // Listen to real-time Reverb broadcasts
    #[On('echo:create-new-post-event,.CreateNewPostEvent')]
    public function handleNewPostBroadcast()
    {
        if (! request()->routeIs('feed')) {
            $this->unreadCount++;
        }
    }

    // Reset when the user clicks the link
    public function markAsRead()
    {
        auth()->user()?->update(['last_feed_viewed_at' => now()]);
        $this->unreadCount = 0;
    }

    public function render()
    {
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
}
?>

<div>
    <x-dashboard.sidebar-menu.sidebar-item 
        href="{{ route('feed') }}" 
        icon="squares-2x2"
        :badge="$unreadCount > 0 ? ($unreadCount > 99 ? '99+' : $unreadCount) : null"
        wire:click="markAsRead"
    >
        Feed
    </x-dashboard.sidebar-menu.sidebar-item>
</div>