<x-layouts::public-layout>
    <div class="relative w-full">
        <div class="relative w-full min-h-[380px] sm:min-h-[460px] md:min-h-[560px] py-12 sm:py-16 md:py-20 md:pb-24 lg:pb-28">
            <x-public.image-overlay />

            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10 flex flex-col items-center justify-center text-center text-white">
                <flux:subheading class="!font-secondary !text-nav-label !uppercase !tracking-[0.2em] !text-white/60 !mb-3 sm:!mb-4">
                    Welcome to
                </flux:subheading>

                <flux:heading size="3xl" class="!font-primary !font-extrabold !text-4xl md:!text-5xl lg:!text-6xl !leading-tight !max-w-3xl !text-balance !text-white">
                    Iriga City Central Terminal's 
                    <span class="text-gradient-radial">SmartICCT</span>
                </flux:heading>

                <flux:text class="!text-body !text-white/70 !mt-3 sm:!mt-4 !max-w-md">
                    Your one-stop for your adventures ahead!
                </flux:text>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-6 sm:mt-8 w-full sm:w-auto max-w-xs sm:max-w-none">
                    <flux:button href="#" wire:navigate icon="arrow-right" icon-position="after" class="!bg-secondary !border-none !text-white">
                        Live Queue
                    </flux:button>
                    <flux:button href="{{ route('route') }}" wire:navigate variant="outline" class="!bg-white/10 hover:!bg-white/20 !border !border-white/40 !text-white !backdrop-blur-sm">
                        View Routes
                    </flux:button>
                </div>
            </div>
            
            <div class="hidden md:block absolute left-1/2 -translate-x-1/2 -bottom-20 lg:-bottom-20 z-10 w-full max-w-5xl lg:max-w-6xl px-6">
                <flux:card class="!p-5 sm:!p-8 md:!p-10 dark:!bg-dark-secondary">
                    <div class="grid grid-cols-3 divide-x divide-light-bd-default dark:divide-dark-bd-default">
                        @php
                            $stats = [
                                ['icon' => 'users', 'label' => 'With over', 'value' => '1,200+ commuters', 'sublabel' => 'Daily!'],
                                ['icon' => 'truck', 'label' => 'Average', 'value' => '85+ trips', 'sublabel' => 'Daily!'],
                                ['icon' => 'envelope', 'label' => 'Total', 'value' => '2,000+ users', 'sublabel' => 'Registered', 'highlight' => true],
                            ];
                        @endphp
                        @foreach($stats as $stat)
                            <div class="flex flex-col items-center text-center px-3 sm:px-4">
                                <span class="!w-10 !h-10 rounded-full !bg-light-subtle dark:!bg-dark-subtle flex items-center justify-center mb-2 shrink-0">
                                    <flux:icon name="{{ $stat['icon'] }}" class="!w-5 !h-5 !text-primary dark:!text-white" />
                                </span>
                                <flux:text size="xs" class="!text-stat-label !text-light-txt-muted dark:!text-dark-txt-muted">
                                    {{ $stat['label'] }}
                                </flux:text>
                                <flux:heading size="xl" class="!font-primary !font-extrabold !text-stat-value !text-light-txt-primary dark:!text-dark-txt-primary !whitespace-nowrap">
                                    {{ $stat['value'] }}
                                </flux:heading>
                                <flux:text size="xs" class="!font-secondary !text-stat-label {{ isset($stat['highlight']) ? '!text-secondary' : '!text-light-txt-muted dark:!text-dark-txt-muted' }}">
                                    {{ $stat['sublabel'] }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        </div>

        <div class="md:hidden px-6 -mt-6 relative z-10">
            <flux:card class="!p-5 !py-4 dark:!bg-dark-secondary">
                <div class="grid grid-cols-3 divide-x divide-light-bd-default dark:divide-dark-bd-default">
                    @php
                        $mobileStats = [
                            ['icon' => 'users', 'value' => '1,200+', 'label' => 'commuters'],
                            ['icon' => 'truck', 'value' => '85+', 'label' => 'trips daily'],
                            ['icon' => 'envelope', 'value' => '2,000+', 'label' => 'registered', 'highlight' => true],
                        ];
                    @endphp
                    @foreach($mobileStats as $stat)
                        <div class="flex flex-col items-center text-center px-1">
                            <span class="!w-6 !h-6 rounded-full !bg-light-subtle dark:!bg-dark-subtle flex items-center justify-center mb-1 shrink-0">
                                <flux:icon name="{{ $stat['icon'] }}" class="!w-3 !h-3 !text-primary dark:!text-white" />
                            </span>
                            <flux:heading size="sm" class="!font-primary !font-bold !text-sm !text-light-txt-primary dark:!text-dark-txt-primary !leading-tight">
                                {{ $stat['value'] }}
                            </flux:heading>
                            <flux:text size="xs" class="!font-secondary !text-[10px] !leading-tight {{ isset($stat['highlight']) ? '!text-secondary' : '!text-light-txt-muted dark:!text-dark-txt-muted' }}">
                                {{ $stat['label'] }}
                            </flux:text>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>
    </div>

    <div class="w-full !pt-14 sm:!pt-20 md:!pt-32 lg:!pt-48 !pb-14 sm:!pb-20 relative overflow-hidden">
        <div class="pointer-events-none absolute -bottom-32 -right-32 sm:-bottom-48 sm:-right-48 w-[420px] h-[420px] sm:w-[600px] sm:h-[600px] rounded-full blur-3xl opacity-30 dark:opacity-20 z-0"
             style="background: radial-gradient(circle at center, var(--color-secondary) 0%, transparent 70%);"
             aria-hidden="true">
        </div>

        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">

                <div class="flex justify-center" style="perspective: 1800px;">
                    <div
                        x-data="{
                            dragging: false, rotX: 0, rotY: 0, startX: 0, startY: 0,
                            baseX: 8, baseY: -14,
                            start(e) {
                                this.dragging = true;
                                this.startX = e.touches ? e.touches[0].clientX : e.clientX;
                                this.startY = e.touches ? e.touches[0].clientY : e.clientY;
                            },
                            move(e) {
                                if (!this.dragging) return;
                                const x = e.touches ? e.touches[0].clientX : e.clientX;
                                const y = e.touches ? e.touches[0].clientY : e.clientY;
                                this.rotY = (x - this.startX) * 0.6;
                                this.rotX = -(y - this.startY) * 0.6;
                            },
                            end() { this.dragging = false; this.rotX = 0; this.rotY = 0; }
                        }"
                        @mousedown="start($event)" @touchstart="start($event)"
                        @mousemove.window="move($event)" @touchmove.window="move($event)"
                        @mouseup.window="end()" @touchend.window="end()"
                        class="w-full max-w-[320px] aspect-[320/208] select-none cursor-grab active:cursor-grabbing"
                        :class="!dragging && 'transition-transform duration-500 ease-out'"
                        :style="`touch-action:none; transform-style:preserve-3d;
                            transform: rotateX(${baseX + rotX}deg) rotateY(${baseY + rotY}deg) rotateZ(3deg);`"
                    >
                        <div class="absolute inset-0 rounded-2xl shadow-2xl overflow-hidden"
                             style="backface-visibility:hidden;-webkit-backface-visibility:hidden;">
                            <img src="{{ asset('images/card_front.svg') }}" alt="Card front"
                                 class="w-full h-full object-cover pointer-events-none" draggable="false">
                        </div>
                        <div class="absolute inset-0 rounded-2xl shadow-2xl overflow-hidden"
                             style="transform:rotateY(180deg);backface-visibility:hidden;-webkit-backface-visibility:hidden;">
                            <img src="{{ asset('images/card_back.svg') }}" alt="Card back"
                                 class="w-full h-full object-cover pointer-events-none" draggable="false">
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-5 sm:gap-6 text-center lg:text-left items-center lg:items-start">
                    <flux:heading class="!font-extrabold font-primary !text-2xl sm:!text-3xl lg:!text-4xl">
                        <span class="!text-primary dark:!text-white">One Smart Card.</span><br>
                        <span class="!text-secondary">Every trip covered.</span>
                    </flux:heading>

                    <flux:text class="!text-sm sm:!text-lg !leading-relaxed !max-w-md !text-light-txt-muted dark:!text-dark-txt-muted">
                        The ICCT Card is a personalized IoT-enabled transit card built for our
                        terminal's ecosystem. Commuters tap to pay their fare. Operators use it for
                        fee tickets. All from one reloadable card.
                    </flux:text>

                    <div class="flex flex-col gap-5 mt-2 w-full max-w-md text-left">
                        <div class="flex items-start gap-4">
                            <span class="shrink-0 !w-10 !h-10 rounded-full !bg-primary !text-white flex items-center justify-center">
                                <flux:icon name="wallet" class="!w-5 !h-5" />
                            </span>
                            <div>
                                <flux:heading size="sm" class="!font-semibold !text-light-txt-primary dark:!text-dark-txt-primary">Cashless fare payments</flux:heading>
                                <flux:text size="sm" class="!text-light-txt-muted dark:!text-dark-txt-muted">Tap at any terminal reader — no fumbling for coins or change.</flux:text>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <span class="shrink-0 !w-10 !h-10 rounded-full !bg-primary !text-white flex items-center justify-center">
                                <flux:icon name="credit-card" class="!w-5 !h-5" />
                            </span>
                            <div>
                                <flux:heading size="sm" class="!font-semibold !text-light-txt-primary dark:!text-dark-txt-primary">Reloadable anytime</flux:heading>
                                <flux:text size="sm" class="!text-light-txt-muted dark:!text-dark-txt-muted">Top up online or at the terminal kiosk in seconds.</flux:text>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <span class="shrink-0 !w-10 !h-10 rounded-full !bg-primary !text-white flex items-center justify-center">
                                <flux:icon name="users" class="!w-5 !h-5" />
                            </span>
                            <div>
                                <flux:heading size="sm" class="!font-semibold !text-light-txt-primary dark:!text-dark-txt-primary">One card for commuters and operators</flux:heading>
                                <flux:text size="sm" class="!text-light-txt-muted dark:!text-dark-txt-muted">Fare payments and operator fee tickets on a single, secure card.</flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                        <flux:button href="#" icon="arrow-right" icon-position="after" class="!w-full sm:!w-auto !bg-secondary hover:!bg-secondary-hover !text-white">
                            Get your card
                        </flux:button>
                        <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                            Load up via <span class="!font-semibold !text-light-txt-body dark:!text-dark-txt-body">GCash</span>, <span class="!font-semibold !text-light-txt-body dark:!text-dark-txt-body">Maya</span>, <span class="!font-semibold !text-light-txt-body dark:!text-dark-txt-body">GrabPay</span> or card &middot; powered by PayMongo
                        </flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- ── RENT VEHICLES SECTION ── --}}
<div class="w-full !py-10 sm:!py-14 md:!py-20 lg:!py-24 relative overflow-hidden">
    <!-- Gradient background (primary) -->
    <div class="pointer-events-none absolute -bottom-32 -left-32 sm:-bottom-48 sm:-left-48 w-[420px] h-[420px] sm:w-[600px] sm:h-[600px] rounded-full blur-3xl opacity-20 dark:opacity-10 z-0"
         style="background: radial-gradient(circle at center, var(--color-primary) 0%, transparent 70%);"
         aria-hidden="true">
    </div>

    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="flex flex-col lg:flex-row lg:items-center justify-center gap-6 lg:gap-8">

            {{-- Copy + CTAs --}}
            <div class="w-full lg:max-w-xl flex flex-col justify-center">
                <flux:heading class="!font-primary !font-extrabold !text-2xl sm:!text-3xl lg:!text-4xl !leading-tight !text-light-txt-primary dark:!text-dark-txt-primary">
                    Rent vehicles for your trips and
                    <span class="!text-secondary">adventures ahead!</span>
                </flux:heading>
                <flux:text class="!text-light-txt-muted dark:!text-dark-txt-muted !mt-2 !mb-6">
                    Right on the Feed, alongside terminal announcements.
                </flux:text>

                <div class="flex items-start gap-3 mb-4">
                    <span class="!w-8 !h-8 rounded-lg !bg-primary flex items-center justify-center shrink-0">
                        <flux:icon name="magnifying-glass" class="!w-4 !h-4 !text-white" />
                    </span>
                    <div>
                        <flux:heading size="sm" class="!font-bold !text-light-txt-primary dark:!text-dark-txt-primary">
                            Need a ride
                        </flux:heading>
                        <flux:link href="{{ route('feed') }}" wire:navigate class="!inline-flex !items-center !gap-1 !text-sm !font-bold !text-primary dark:!text-white hover:underline">
                            Post a request <flux:icon name="arrow-right" class="!w-3.5 !h-3.5" />
                        </flux:link>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <span class="!w-8 !h-8 rounded-lg !bg-secondary flex items-center justify-center shrink-0">
                        <flux:icon name="truck" class="!w-4 !h-4 !text-white" />
                    </span>
                    <div>
                        <flux:heading size="sm" class="!font-bold !text-light-txt-primary dark:!text-dark-txt-primary">
                            Got a vehicle
                        </flux:heading>
                        <flux:text size="sm" class="!text-light-txt-muted dark:!text-dark-txt-muted !mb-1">
                            Requires an operator account. New here?
                            <flux:link href="{{ route('help.center') }}" wire:navigate class="!text-light-txt-muted dark:!text-dark-txt-muted !underline hover:!text-primary">See the registration guide</flux:link>.
                        </flux:text>
                        <flux:link
                            href="{{ auth()->user() && auth()->user()->role === 'operator' ? route('feed') : route('login') }}"
                            wire:navigate
                            class="!inline-flex !items-center !gap-1 !text-sm !font-bold !text-primary dark:!text-white hover:underline"
                        >
                            List your vehicle <flux:icon name="arrow-right" class="!w-3.5 !h-3.5" />
                        </flux:link>
                    </div>
                </div>
            </div>

            {{-- Mock feed preview – now stays at a comfortable width --}}
            <div class="w-full max-w-[320px] mx-auto lg:mx-0 shrink-0 space-y-4">
                <!-- Card 1: Request (Maya) -->
                <div class="bg-light-secondary dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="!w-7 !h-7 rounded-full !bg-light-subtle dark:!bg-dark-subtle flex items-center justify-center text-[11px] font-bold text-light-txt-primary dark:text-dark-txt-primary shrink-0">MR</span>
                        <div class="flex-1 min-w-0">
                            <flux:text size="xs" class="!font-bold !text-light-txt-primary dark:!text-dark-txt-primary !block !truncate">Maya R.</flux:text>
                            <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted">2h ago</flux:text>
                        </div>
                        <flux:badge color="blue" size="sm" class="font-secondary !text-[10px] shrink-0">Request</flux:badge>
                    </div>
                    <flux:text size="sm" class="!text-light-txt-body dark:!text-dark-txt-body !leading-snug">
                        Need a van this Saturday for a family trip to Bato. Anyone available?
                    </flux:text>
                </div>

                <!-- Card 2: For rent (Rico) -->
                <div class="bg-light-secondary dark:bg-dark-secondary border border-light-bd-default dark:border-dark-bd-default rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="!w-7 !h-7 rounded-full !bg-secondary/15 flex items-center justify-center text-[11px] font-bold text-secondary shrink-0">RT</span>
                        <div class="flex-1 min-w-0">
                            <flux:text size="xs" class="!font-bold !text-light-txt-primary dark:!text-dark-txt-primary !block !truncate">Rico's Transport</flux:text>
                            <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted">Operator</flux:text>
                        </div>
                        <flux:badge color="green" size="sm" class="font-secondary !text-[10px] shrink-0">For rent</flux:badge>
                    </div>
                    <flux:text size="sm" class="!text-light-txt-body dark:!text-dark-txt-body !leading-snug">
                        Multicab available for day trips, &#8369;1,500/day, driver included.
                    </flux:text>
                </div>
            </div>
        </div>
    </div>
</div>

    {{-- ── PINNED ANNOUNCEMENTS SECTION ── --}}
    @php
        $announcements = \App\Models\Post::with('user')
            ->where('type', 'announcement')
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get();
    @endphp

    @if ($announcements->isNotEmpty())
        <div class="w-full !py-14 sm:!py-20 bg-light-secondary/50 dark:bg-dark-secondary/30 border-t border-light-bd-default dark:border-dark-bd-default">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">

                {{-- Section heading --}}
                <div class="flex items-center gap-3 mb-8 sm:mb-10">
                    <span class="!w-9 !h-9 rounded-full !bg-secondary/15 flex items-center justify-center shrink-0">
                        <flux:icon name="megaphone" class="!w-4.5 !h-4.5 !text-secondary" />
                    </span>
                    <flux:heading class="!font-primary !font-extrabold !text-2xl sm:!text-3xl lg:!text-4xl !text-light-txt-primary dark:!text-dark-txt-primary">
                        Latest Terminal Announcements
                    </flux:heading>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                    {{-- Featured post --}}
                    @php
                        $featured = $announcements->first();
                        $featuredImg = !empty($featured->metadata['attachments'][0])
                            ? Storage::url($featured->metadata['attachments'][0])
                            : null;
                    @endphp

                    <flux:card class="lg:col-span-3 !p-0 overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200">
                        <div class="grid grid-cols-1 {{ $featuredImg ? 'sm:grid-cols-2' : '' }} h-full">
                            @if ($featuredImg)
                                <div class="relative min-h-[220px] sm:min-h-full">
                                    <img src="{{ $featuredImg }}" alt="Announcement image"
                                        class="absolute inset-0 w-full h-full object-cover">
                                </div>
                            @endif

                            <div class="flex flex-col justify-center gap-3 p-6 {{ $featuredImg ? '' : 'sm:p-8' }}">
                                <span class="inline-flex items-center gap-1.5 w-fit">
                                    <span class="!w-6 !h-6 rounded-full !bg-primary/10 dark:!bg-white/10 flex items-center justify-center shrink-0">
                                        <flux:icon name="clock" class="!w-3.5 !h-3.5 !text-primary dark:!text-white" />
                                    </span>
                                    <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                                        {{ $featured->created_at->diffForHumans(['short' => true]) }}
                                    </flux:text>
                                </span>

                                <flux:heading size="lg" class="!font-primary !font-bold !text-light-txt-primary dark:!text-dark-txt-primary !leading-snug">
                                    {{ \Illuminate\Support\Str::limit($featured->body, 70) }}
                                </flux:heading>

                                <flux:text size="sm" class="!text-light-txt-muted dark:!text-dark-txt-muted !leading-relaxed">
                                    {{ \Illuminate\Support\Str::limit($featured->body, $featuredImg ? 160 : 240) }}
                                </flux:text>

                                <flux:button href="{{ route('login') }}" wire:navigate
                                    icon="arrow-top-right-on-square" icon-position="after"
                                    class="!bg-primary hover:!bg-primary-hover !text-white !w-fit !mt-2">
                                    View Announcement
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>

                    {{-- Two smaller posts --}}
                    <div class="lg:col-span-2 grid grid-cols-1 gap-6">
                        @foreach ($announcements->skip(1)->take(2) as $post)
                            @php
                                $img = !empty($post->metadata['attachments'][0])
                                    ? Storage::url($post->metadata['attachments'][0])
                                    : null;
                            @endphp

                            <flux:card wire:key="pinned-{{ $post->id }}" class="!p-0 overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200">
                                <a href="{{ route('login') }}" wire:navigate class="flex items-center gap-4 p-5">
                                    @if ($img)
                                        <img src="{{ $img }}" alt="Announcement image"
                                            class="!w-20 !h-16 sm:!w-24 sm:!h-20 rounded-xl object-cover shrink-0">
                                    @else
                                        <span class="!w-11 !h-11 rounded-full !bg-secondary/15 flex items-center justify-center shrink-0">
                                            <flux:icon name="megaphone" class="!w-5 !h-5 !text-secondary" />
                                        </span>
                                    @endif

                                    <div class="flex flex-col gap-1 min-w-0">
                                        <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted !flex !items-center !gap-1">
                                            <flux:icon name="clock" class="!size-3" />
                                            {{ $post->created_at->diffForHumans(['short' => true]) }}
                                        </flux:text>
                                        <flux:text size="sm" class="!font-semibold !text-secondary !leading-snug !line-clamp-2">
                                            {{ \Illuminate\Support\Str::limit($post->body, $img ? 50 : 80) }}
                                        </flux:text>
                                    </div>
                                </a>
                            </flux:card>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── FOOTER ── --}}
    <footer class="bg-light-secondary dark:bg-dark-secondary border-t border-light-bd-default dark:border-dark-bd-default">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-12">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <img src="{{ asset('images/logo.png') }}" alt="SmartICCT" class="h-7 w-auto">
                        <span class="font-primary text-lg font-bold !text-light-txt-primary dark:!text-dark-txt-primary">
                            SmartICCT
                        </span>
                    </div>
                    <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted !leading-relaxed !max-w-xs">
                        Iriga City Central Terminal's digital ecosystem. Real-time queueing, reloadable cards, and seamless travel.
                    </flux:text>
                </div>

                {{-- Quick Links --}}
                <div>
                    <flux:heading size="xs" class="!font-semibold !uppercase !tracking-wide !mb-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                        Quick Links
                    </flux:heading>
                    <nav class="flex flex-col gap-2.5">
                        <flux:link href="/" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Explore
                        </flux:link>
                        <flux:link href="{{ route('live.queue') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Queue
                        </flux:link>
                        <flux:link href="{{ route('route') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Routes
                        </flux:link>
                        <flux:link href="{{ route('feed') }}" wire:navigate class="!text-sm !text-light-txt-muted dark:!text-dark-txt-muted hover:!text-light-txt-primary dark:hover:!text-dark-txt-primary transition-colors duration-150">
                            Feed
                        </flux:link>
                    </nav>
                </div>

                {{-- Contact & Social --}}
                <div>
                    <flux:heading size="xs" class="!font-semibold !uppercase !tracking-wide !mb-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                        Contact
                    </flux:heading>

                    <div class="flex flex-col gap-2.5 mb-5">
                        <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                            San Nicolas, Iriga City, Camarines Sur
                        </flux:text>
                        <flux:text size="md" class="!text-light-txt-muted dark:!text-dark-txt-muted">
                            +63 (54) 456-7890
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-3">
                        <a href="#" aria-label="Facebook"
                        class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                <path d="M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram"
                        class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                <path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.21.6 1.76 1.15.55.55.9 1.1 1.15 1.76.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43-.26.66-.6 1.21-1.15 1.76a4.9 4.9 0 0 1-1.76 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.76-1.15 4.9 4.9 0 0 1-1.15-1.76c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.21 1.15-1.76A4.9 4.9 0 0 1 5.44 2.53c.64-.25 1.37-.42 2.43-.47C8.94 2.01 9.28 2 12 2zm0 1.8c-2.67 0-2.99.01-4.04.06-.97.04-1.5.2-1.85.34-.46.18-.79.4-1.14.74-.34.35-.56.68-.74 1.14-.14.35-.3.88-.34 1.85-.05 1.05-.06 1.37-.06 4.04s.01 2.99.06 4.04c.04.97.2 1.5.34 1.85.18.46.4.79.74 1.14.35.34.68.56 1.14.74.35.14.88.3 1.85.34 1.05.05 1.37.06 4.04.06s2.99-.01 4.04-.06c.97-.04 1.5-.2 1.85-.34.46-.18.79-.4 1.14-.74.34-.35.56-.68.74-1.14.14-.35.3-.88.34-1.85.05-1.05.06-1.37.06-4.04s-.01-2.99-.06-4.04c-.04-.97-.2-1.5-.34-1.85a3.08 3.08 0 0 0-.74-1.14 3.08 3.08 0 0 0-1.14-.74c-.35-.14-.88-.3-1.85-.34-1.05-.05-1.37-.06-4.04-.06zm0 4.59a4.61 4.61 0 1 1 0 9.22 4.61 4.61 0 0 1 0-9.22zm0 1.8a2.81 2.81 0 1 0 0 5.62 2.81 2.81 0 0 0 0-5.62zm5.88-1.99a1.08 1.08 0 1 1-2.16 0 1.08 1.08 0 0 1 2.16 0z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Messenger"
                        class="flex items-center justify-center !size-8 rounded-full !bg-light-bd-default dark:!bg-dark-bd-default hover:!bg-light-bd-strong dark:hover:!bg-dark-bd-strong transition-colors duration-150">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="!size-4 !text-light-txt-primary dark:!text-dark-txt-primary">
                                <path d="M12 2C6.48 2 2 6.15 2 11.27c0 2.91 1.45 5.5 3.72 7.21V22l3.4-1.87c.91.25 1.87.39 2.88.39 5.52 0 10-4.15 10-9.27S17.52 2 12 2zm1.01 12.49-2.55-2.72-4.97 2.72 5.47-5.8 2.61 2.72 4.91-2.72-5.47 5.8z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <flux:separator class="!my-8 dark:!border-dark-bd-default" />

            <flux:text size="xs" class="!text-light-txt-muted dark:!text-dark-txt-muted !flex !items-center !gap-1.5">
                <flux:icon name="globe-alt" class="!size-3.5" />
                2026 Iriga City Central Terminal. SmartICCT
            </flux:text>
        </div>
    </footer>
</x-layouts::public-layout>