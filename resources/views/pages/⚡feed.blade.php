<?php

namespace App\Livewire\Pages;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Post;
use App\Models\RentalOffer;
use App\Models\TripRequest;
use App\Models\PostInterest;
use Flux\Flux;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithPagination;

    public ?int $selectedPostId = null;
    public $selected_post;
    public bool $show_trip_request_modal = false;
    public bool $show_delete_interested_modal = false;
    public $replies;
    public bool $showRepliesModal = false;
    public bool $rental_offer_modal = false;
    public bool $show_delete_post_modal = false;
    public ?int $deletePostId = null;

    public string $filterRole = 'all';
    public string $filterVehicleType = 'all';
    public string $filterType = 'all';
    public string $dateRange = 'all';
    public string $searchQuery = '';

    public string $tempFilterRole = 'all';
    public string $tempFilterVehicleType = 'all';
    public string $tempFilterType = 'all';
    public string $tempDateRange = 'all';

    public $name;
    public $phone_number;

    #[On('interest-deleted')]
    #[On('interested-list-updated')]
    public function closeModals()
    {
        $this->show_delete_interested_modal = false;
        $this->show_trip_request_modal = false;
        $this->rental_offer_modal = false;
        $this->selected_post = null;
        unset($this->filteredPosts);
        unset($this->myInterestedPostIds);
        unset($this->myDeclinedPostIds);
    }

    #[On('echo:create-new-post-event,.CreateNewPostEvent')]
    #[On('new-post-created')]
    public function refreshFeed() {
        unset($this->filteredPosts);
        unset($this->announcements);
    }

    public function showRepliesToYouModal($interest_id) {
        $this->showRepliesModal = true;
        $this->replies = PostInterest::where('id', $interest_id)->first();
    }

    public function mount() {
        if (auth()->check()) {
            $this->name = auth()->user()->name;
            $this->phone_number = auth()->user()->phone_number;
        }
        $this->syncTemps();
    }

    private function syncTemps() {
        $this->tempFilterRole = $this->filterRole;
        $this->tempFilterVehicleType = $this->filterVehicleType;
        $this->tempFilterType = $this->filterType;
        $this->tempDateRange = $this->dateRange;
    }

    public function applyFilters() {
        $this->filterRole = $this->tempFilterRole;
        $this->filterVehicleType = $this->tempFilterVehicleType;
        $this->filterType = $this->tempFilterType;
        $this->dateRange = $this->tempDateRange;
        $this->resetPage();
        unset($this->filteredPosts);
        $this->dispatch('filters-applied');
    }

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    public function canExpressInterest(Post $post): bool
    {
        $user = auth()->user();
        if (! $user) return false;
        if ($user->id === $post->user_id) return false;
        if ($post->status !== 'published') return false;
        if ($post->type === 'announcement') {
            return in_array($user->role, ['admin', 'cashier']);
        }
        if ($post->type === 'rental') {
            if ($user->role === 'operator') return $post->user->role === 'commuter';
            if ($user->role === 'commuter') return $post->user->role === 'operator';
        }
        return false;
    }

    public function getPostInterested($postId) {
        $this->selectedPostId = null;
        $this->selectedPostId = $postId;
    }

    #[On('echo:notification-event,.NotificationEvent')]
    public function reloadNotification() {
        $this->selected_post = null;
        $this->rental_offer_modal = false;
    }

    public function rentalOfferModal($postId) {
        $this->selected_post = null;
        $this->rental_offer_modal = false;
        $this->selected_post = Post::with('user')->find($postId);
        $this->rental_offer_modal = true;
    }

    public function tripRequest($postId) {
        $this->selected_post = null;
        $this->show_delete_interested_modal = false;
        $this->selected_post = Post::with('user')->find($postId);
        $this->show_trip_request_modal = true;
    }

    public function uninterested($postId) {
        $this->selected_post = null;
        if (auth()->guest()) return;
        if(auth()->user()->role === 'operator') {
            $this->selected_post = RentalOffer::where('user_id', auth()->id())
                ->where('post_id', $postId)->first();
        } elseif(auth()->user()->role === 'commuter') {
            $this->selected_post = TripRequest::where('user_id', auth()->id())
                ->where('post_id', $postId)->first();
        }
        $this->show_delete_interested_modal = true;
    }

    public function markAsRented($postId) {
        $post = Post::findOrFail($postId);
        if (auth()->check() && $post->user_id === auth()->id() && $post->type === 'rental') {
            $post->update(['status' => 'rented']);

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Post marked as rented',
                text: 'Your post has been marked as rented.',
            );
        }
    }

    public function archivePost($postId) {
        $post = Post::findOrFail($postId);

        if (! auth()->check()) {
            return;
        }

        $isOwner = $post->user_id === auth()->id();
        $canModerate = auth()->user()->role === 'admin' && $post->type === 'rental' && ! $isOwner;

        if (! $isOwner && ! $canModerate) {
            return;
        }

        $post->update(['status' => 'archived']);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Post archived',
            text: $canModerate
                ? 'This post has been archived and removed from the feed.'
                : 'Your post has been archived and removed from the feed.',
        );
    }

    public function restorePost($postId) {
        $post = Post::findOrFail($postId);
        if (auth()->check() && $post->user_id === auth()->id()) {
            $post->update(['status' => 'published']);

            Flux::toast(
                duration: 0,
                variant: 'success',
                heading: 'Post restored',
                text: 'Your post is back in the feed.',
            );
        }
    }

    public function confirmDeletePost($postId) {
        if (! auth()->check()) {
            return;
        }

        $post = Post::findOrFail($postId);

        if ($post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            return;
        }

        $hasActiveInterest = $post->tripRequest()->whereIn('status', ['pending', 'accept'])->exists()
            || $post->rentalOffer()->whereIn('status', ['pending', 'accept'])->exists();

        if ($hasActiveInterest) {
            Flux::toast(
                duration: 0,
                variant: 'danger',
                heading: 'Cannot delete this post',
                text: 'This post has pending or active requests. Resolve or cancel those first.',
            );
            return;
        }

        $this->deletePostId = $postId;
        $this->show_delete_post_modal = true;
    }

    public function cancelDeletePost() {
        $this->deletePostId = null;
        $this->show_delete_post_modal = false;
    }

    public function deletePost() {
        if (! $this->deletePostId) {
            $this->show_delete_post_modal = false;
            return;
        }

        $post = Post::find($this->deletePostId);

        if (! $post || $post->user_id !== auth()->id() || ! in_array(auth()->user()->role, ['operator', 'commuter'])) {
            $this->deletePostId = null;
            $this->show_delete_post_modal = false;
            return;
        }

        $hasActiveInterest = $post->tripRequest()->whereIn('status', ['pending', 'accept'])->exists()
            || $post->rentalOffer()->whereIn('status', ['pending', 'accept'])->exists();

        if ($hasActiveInterest) {
            $this->deletePostId = null;
            $this->show_delete_post_modal = false;
            return;
        }

        // Attachments aren't removed here — the post is only soft-deleted, so it
        // can still be restored from Trash. They're cleaned up by the daily purge
        // job in routes/console.php once the post is 30 days old.
        $post->delete();

        $this->deletePostId = null;
        $this->show_delete_post_modal = false;

        unset($this->filteredPosts);

        Flux::toast(
            duration: 0,
            variant: 'success',
            heading: 'Post moved to Trash',
            text: 'You can restore it within 30 days, from the Trash page.',
        );
    }

    #[Computed]
    public function vehicleTypeOptions()
    {
        return Post::where('type', 'rental')
            ->whereNotNull('metadata->vehicle_type')
            ->get(['metadata'])
            ->pluck('metadata.vehicle_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function filteredPosts()
    {
        $query = Post::with('user', 'tripRequest', 'rentalOffer')
            ->whereIn('status', ['published', 'rented']);

        if ($this->filterType === 'rental') {
            $query->where('type', 'rental');
        } elseif ($this->filterType === 'announcement') {
            $query->where('type', 'announcement');
        }

        if ($this->filterType !== 'announcement') {
            $query->withCount([
                'tripRequest' => fn($q) => $q->whereIn('status',['pending','cancel']),
                'rentalOffer' => fn($q) => $q->whereIn('status',['pending','cancel'])
            ]);
        }

        if ($this->filterRole !== 'all') {
            $query->whereHas('user', function($q) {
                $q->where('role', $this->filterRole);
            });
        }

        if ($this->filterVehicleType !== 'all') {
            $query->where('metadata->vehicle_type', $this->filterVehicleType);
        }

        if ($this->dateRange !== 'all') {
            $days = (int) $this->dateRange;
            $query->whereDate('created_at', '>=', today()->subDays($days - 1));
        }

        if (!empty($this->searchQuery)) {
            $search = '%' . $this->searchQuery . '%';
            $query->where(function($q) use ($search) {
                $q->where('body', 'LIKE', $search)
                  ->orWhereHas('user', function($sub) use ($search) {
                      $sub->where('name', 'LIKE', $search);
                  });
            });
        }

        // Guest: only show first 5 posts
        if (auth()->guest()) {
            return $query->latest()->take(5)->get();
        }

        return $query->latest()->paginate(10);
    }

    #[Computed]
    public function announcements()
    {
        return Post::with('user')
            ->where('type', 'announcement')
            ->where('status', 'published')
            ->latest()->get();
    }

    #[Computed]
    public function myInterestedPostIds() {
        if (auth()->guest()) return [];
        if(auth()->user()->role === 'operator') {
            return RentalOffer::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'accept'])
                ->pluck('post_id')->all();
        } elseif(auth()->user()->role === 'commuter') {
            return TripRequest::where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'accept'])
                ->pluck('post_id')->all();
        }
        return [];
    }

    #[Computed]
    public function myDeclinedPostIds() {
        if (auth()->guest()) return [];
        if(auth()->user()->role === 'operator') {
            return RentalOffer::where('user_id', auth()->id())
                ->whereIn('status', ['cancel', 'decline'])
                ->pluck('post_id')->all();
        } elseif(auth()->user()->role === 'commuter') {
            return TripRequest::where('user_id', auth()->id())
                ->whereIn('status', ['cancel', 'decline'])
                ->pluck('post_id')->all();
        }
        return [];
    }

    #[Computed]
    public function myInterests() {
        if (auth()->guest()) return collect();
        return PostInterest::with('post.user')
            ->where('user_id', auth()->id())
            ->latest()->get();
    }

    #[Computed]
    public function activeInterests() {
        if (auth()->guest() || !$this->selectedPostId) return collect();
        $post = Post::find($this->selectedPostId);
        if (!$post || $post->user_id !== auth()->id()) return collect();
        return PostInterest::with('user', 'post')
            ->where('post_id', $this->selectedPostId)
            ->where(fn($q) => $q->where('status', '!=', 'decline')->orWhereNull('status'))
            ->latest()->get();
    }

    public function resetFilters()
    {
        $this->filterRole = 'all';
        $this->filterVehicleType = 'all';
        $this->filterType = 'all';
        $this->dateRange = 'all';
        $this->searchQuery = '';
        $this->syncTemps();
        $this->resetPage();
        unset($this->filteredPosts);
    }

    public function render() {
        if (auth()->guest()) {
            return $this->view()->layout('layouts.public-layout');
        }
        $role = auth()->user()->role;
        return $this->view()->layout('layouts.' . $role . '-layout');
    }
};
?>

<div class="{{ auth()->guest() ? 'mx-auto max-w-5xl px-4 py-8 sm:px-10' : '' }}">
    <div class="flex flex-wrap items-start justify-between gap-2 sm:gap-3 mb-4 sm:mb-6">
        <div>
            <x-heading
                size="xl"
                class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary"
                style="font-size: var(--text-page-title)"
            >
                Feed
            </x-heading>
            <x-text variant="subtle" class="!font-secondary mt-1 block" style="font-size: var(--text-helper)">
                Rental posts from commuters and operators, with admin announcements pinned alongside.
            </x-text>
        </div>

        @auth
            <div class="flex items-center gap-1 sm:gap-2 flex-wrap mt-1 sm:mt-0">
                <x-button
                    href="{{ route('post.archived') }}"
                    wire:navigate
                    variant="ghost"
                    icon="archive-box"
                    class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2
                        !border !border-light-bd-default dark:!border-dark-bd-default
                        !text-light-txt-primary dark:!text-dark-txt-primary
                        hover:!bg-light-subtle dark:hover:!bg-dark-subtle"
                >
                    <span class="hidden sm:inline">Archived</span>
                    <span class="sm:hidden">Archived</span>
                </x-button>
                <x-button
                    href="{{ route('post.my-posts') }}"
                    wire:navigate
                    variant="ghost"
                    icon="document-text"
                    class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2
                        !border !border-light-bd-default dark:!border-dark-bd-default
                        !text-light-txt-primary dark:!text-dark-txt-primary
                        hover:!bg-light-subtle dark:hover:!bg-dark-subtle"
                >
                    <span class="hidden sm:inline">My posts</span>
                    <span class="sm:hidden">My posts</span>
                </x-button>
                <x-button
                    href="{{ route('post.trash') }}"
                    wire:navigate
                    variant="ghost"
                    icon="trash"
                    class="!font-secondary text-sm sm:text-base !px-2 sm:!px-3 !py-1 sm:!py-2
                        !border !border-light-bd-default dark:!border-dark-bd-default
                        !text-light-txt-primary dark:!text-dark-txt-primary
                        hover:!bg-light-subtle dark:hover:!bg-dark-subtle"
                >
                    <span class="hidden sm:inline">Trash</span>
                    <span class="sm:hidden">Trash</span>
                </x-button>
            </div>
        @endauth
    </div>

    <div class="flex flex-col lg:flex-row gap-4 h-full min-h-0">
        <div class="flex-1 min-w-0 flex flex-col gap-4 min-h-0">
            @auth
                <div class="shrink-0">
                    <livewire:pages::create-post />
                </div>
            @endauth

            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <flux:modal.trigger name="feed-filters">
                    <button
                        type="button"
                        class="relative flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 h-8 sm:h-9 rounded-lg border border-light-bd-default dark:border-dark-bd-default text-light-txt-body dark:text-dark-txt-body hover:bg-light-subtle dark:hover:bg-dark-subtle transition font-secondary text-xs sm:text-table-row shrink-0"
                    >
                        <flux:icon.funnel class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-light-txt-muted dark:text-dark-txt-muted" />
                        <span>Filters</span>
                        @php
                            $activeFilters = ($filterRole !== 'all') + ($filterVehicleType !== 'all') + ($filterType !== 'all') + ($dateRange !== 'all');
                        @endphp
                        @if ($activeFilters > 0)
                            <span class="flex items-center justify-center w-4 h-4 rounded-full bg-primary dark:bg-dark-txt-primary text-white dark:text-dark-bg text-[10px] font-bold">{{ $activeFilters }}</span>
                        @endif
                    </button>
                </flux:modal.trigger>

                <div class="flex-1 min-w-[150px]">
                    <x-input
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Search route or poster"
                        class="w-full !rounded-full !bg-light-primary dark:!bg-dark-subtle !border-light-bd-default dark:!border-dark-bd-default"
                    />
                </div>

                @guest
                    <flux:modal.trigger name="guest-post-modal">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-4 sm:py-2 rounded-full bg-primary text-white hover:bg-primary-hover transition font-secondary text-sm sm:text-base"
                        >
                            <flux:icon.pencil class="w-4 h-4 sm:w-5 sm:h-5" />
                            <span>Post</span>
                        </button>
                    </flux:modal.trigger>
                @endguest
            </div>

            {{-- Posts list --}}
            <div class="flex-1 min-h-0 flex flex-col">
                <div class="flex-1 min-h-0 overflow-y-auto space-y-4 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                    @forelse ($this->filteredPosts as $post)
                        <x-post-card :post="$post" wire:key="post-{{ $post->id }}">
                            <x-slot name="footer">
                                @auth
                                    @php
                                        $isOwner = $post->user_id === auth()->id();
                                        $alreadyInterested = in_array($post->id, $this->myInterestedPostIds);
                                        $isDeclined = in_array($post->id, $this->myDeclinedPostIds);
                                        $role = auth()->user()->role;
                                    @endphp
                                    @include("partials.feed.actions.{$role}", [
                                        'post' => $post,
                                        'isOwner' => $isOwner,
                                        'isDeclined' => $isDeclined,
                                    ])
                                @else
                                    <span class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                        <a href="{{ route('login') }}" class="text-primary hover:underline">Login</a> to interact
                                    </span>
                                @endauth
                            </x-slot>
                        </x-post-card>
                    @empty
                        <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-8">
                            <flux:icon name="newspaper" class="w-8 h-8 mx-auto text-light-txt-muted dark:text-dark-txt-muted mb-2" />
                            <x-text variant="subtle" class="!font-secondary block" style="font-size: var(--text-table-row)">
                                No posts match your filters.
                            </x-text>
                            <x-text variant="subtle" class="!font-secondary block mt-1" style="font-size: var(--text-timestamp)">
                                Try adjusting the search or filter.
                            </x-text>
                        </x-card>
                    @endforelse

                    @guest
                        @if ($this->filteredPosts->isNotEmpty())
                            <div class="text-center py-4 border-t border-light-bd-default dark:border-dark-bd-default">
                                <p class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                    Showing the latest posts.
                                    <a href="{{ route('login') }}" class="text-primary hover:underline font-medium">Log in</a> to see all.
                                </p>
                            </div>
                        @endif
                    @endguest
                </div>

                @auth
                    @if ($this->filteredPosts->hasPages())
                        <div class="shrink-0 pt-4 border-t border-light-bd-default dark:border-dark-bd-default mt-4">
                            {{ $this->filteredPosts->links() }}
                        </div>
                    @endif
                @endauth
            </div>
        </div>

        <div class="w-full lg:w-80 shrink-0 flex flex-col gap-3 min-h-0">
            <x-text variant="strong" class="!font-primary !font-bold !text-xl text-center" style="font-size: var(--text-card-title)">
                Terminal Announcements
            </x-text>

            <div class="flex-1 min-h-0 overflow-y-auto space-y-3 [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                @forelse ($this->announcements as $announcement)
                    <x-post-card :post="$announcement" wire:key="ann-{{ $announcement->id }}" />
                @empty
                    <x-card class="!rounded-xl !border !border-dashed !border-light-bd-strong dark:!border-dark-bd-strong !bg-light-secondary dark:!bg-dark-secondary !text-center !p-4">
                        <x-text variant="subtle" style="font-size: var(--text-timestamp)">No announcements.</x-text>
                    </x-card>
                @endforelse
            </div>
        </div>
    </div>

    <flux:modal name="feed-filters" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Filter posts</flux:heading>
                <flux:subheading>Narrow down the feed.</flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:select wire:model="tempFilterRole" label="User role">
                    <flux:select.option value="all">All users</flux:select.option>
                    <flux:select.option value="operator">Operators</flux:select.option>
                    <flux:select.option value="commuter">Commuters</flux:select.option>
                </flux:select>

                <flux:select wire:model="tempFilterType" label="Post type">
                    <flux:select.option value="all">All posts</flux:select.option>
                    <flux:select.option value="rental">Rental posts</flux:select.option>
                    <flux:select.option value="announcement">Announcements</flux:select.option>
                </flux:select>

                <flux:select wire:model="tempFilterVehicleType" label="Vehicle type">
                    <flux:select.option value="all">All vehicles</flux:select.option>
                    @foreach ($this->vehicleTypeOptions as $type)
                        <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="tempDateRange" label="Date range">
                    <flux:select.option value="all">All time</flux:select.option>
                    <flux:select.option value="7">Last 7 days</flux:select.option>
                    <flux:select.option value="14">Last 14 days</flux:select.option>
                    <flux:select.option value="30">Last 30 days</flux:select.option>
                </flux:select>
            </div>
            <div class="flex items-center justify-between pt-4 border-t border-light-bd-default dark:border-dark-bd-default">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="font-secondary text-table-row text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-primary dark:hover:text-dark-txt-primary transition"
                >
                    Reset all filters
                </button>
                <flux:modal.close>
                    <flux:button variant="primary" wire:click="applyFilters">Done</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    @guest
        <flux:modal name="guest-post-modal" class="md:w-96">
            <div class="space-y-6 text-center py-4">
                <flux:icon.pencil class="w-12 h-12 mx-auto text-primary" />
                <div>
                    <flux:heading size="lg">Create an account to continue</flux:heading>
                    <flux:subheading class="mt-1">
                        You need to be logged in to create posts or interact with the community.
                    </flux:subheading>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 justify-center pt-2">
                    <flux:button variant="primary" href="{{ route('login') }}" wire:navigate>
                        Login
                    </flux:button>
                    <flux:button variant="ghost" href="{{ route('public.register') }}" wire:navigate>
                        Register
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endguest

    <flux:modal wire:model="rental_offer_modal" :closable="false" class="w-full max-w-sm sm:max-w-md md:max-w-lg lg:min-w-196">
        @if ($this->selected_post)
            <livewire:pages::interested-operator-modal
                :selected_post="$selected_post"
                :key="'view-operator-' . $selected_post->id"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="show_trip_request_modal" :closable="false" class="w-full max-w-sm sm:max-w-md md:max-w-lg lg:min-w-196">
        @if ($this->selected_post)
            <livewire:pages::trip-request-modal
                :selected_post="$selected_post"
                :key="'view-' . $selected_post->id"
                :name="auth()->user()->name ?? ''"
                :phone_number="auth()->user()->phone_number ?? ''"
                :email_address="auth()->user()->email_address ?? ''"
                :address="auth()->user()->address ?? ''"
            />
        @endif
    </flux:modal>

    <flux:modal wire:model="show_delete_interested_modal" :closable="false" class="w-full max-w-xs sm:max-w-sm md:max-w-md lg:min-w-96">
        @if ($this->selected_post)
            <livewire:pages::post-delete-interest-modal
                :selected_post="$selected_post"
                :key="'delete-' . $selected_post->id"
            />
        @endif
    </flux:modal>

    <flux:modal
        wire:model="show_delete_post_modal"
        :closable="false"
        class="w-full max-w-[95vw] sm:max-w-lg md:max-w-2xl lg:max-w-3xl mx-auto max-h-[80vh] sm:max-h-[90vh] overflow-hidden rounded-xl"
    >
        <div class="flex flex-col max-h-[calc(80vh-2rem)] sm:max-h-[calc(90vh-2rem)] overflow-y-auto overscroll-contain p-4 sm:p-6 !pr-4 sm:!pr-6 space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="xl" class="!font-primary !font-bold text-light-txt-primary dark:text-dark-txt-primary">
                        Delete this post?
                    </flux:heading>
                    <flux:text class="mt-1 font-secondary text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        This will move the post to Trash. You can restore it within 30 days — after that, it's deleted for good.
                    </flux:text>
                </div>
                <flux:modal.close>
                    <button type="button" class="p-1 rounded-full hover:bg-light-subtle dark:hover:bg-dark-subtle text-light-txt-muted dark:text-dark-txt-muted -mt-1">
                        <flux:icon name="x-mark" class="w-5 h-5" />
                    </button>
                </flux:modal.close>
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end items-stretch sm:items-center gap-2 pt-2 border-t border-light-bd-default dark:border-dark-bd-default">
                <flux:modal.close>
                    <x-button type="button" variant="ghost" wire:click="cancelDeletePost" class="w-full sm:w-auto justify-center !font-secondary">
                        Cancel
                    </x-button>
                </flux:modal.close>
                <x-button
                    wire:click="deletePost"
                    wire:loading.attr="disabled"
                    type="button"
                    variant="primary"
                    color="red"
                    class="w-full sm:w-auto justify-center !font-secondary"
                >
                    Move to Trash
                </x-button>
            </div>
        </div>
    </flux:modal>
</div>