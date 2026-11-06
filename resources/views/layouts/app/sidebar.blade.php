<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-ink-950">
        @php($isAdmin = auth()->user()->is_admin)
        <flux:sidebar sticky collapsible="mobile" class="{{ $isAdmin ? 'dark border-e border-ink-800 bg-ink-950 text-zinc-200' : 'border-e border-zinc-200 bg-white dark:border-white/10 dark:bg-ink-900' }}">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($isAdmin)
                <div class="mx-2 rounded-lg bg-brand-400/15 px-3 py-1.5 text-xs font-semibold tracking-wide text-brand-300 uppercase in-data-flux-sidebar-collapsed-desktop:hidden">{{ __('Admin panel') }}</div>
            @endif

            <flux:sidebar.nav>
                @if ($isAdmin)
                    @php($pending = \App\Support\AdminCounts::pending())
                    <flux:sidebar.group :heading="__('Overview')" class="grid">
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users*')" wire:navigate.hover>{{ __('Users') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="document-magnifying-glass" :href="route('admin.ledger')" :current="request()->routeIs('admin.ledger')" wire:navigate.hover>{{ __('Audit log') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group :heading="__('To review')" class="grid">
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('admin.deposits')" :current="request()->routeIs('admin.deposits')" :badge="$pending['deposits'] ?: null" wire:navigate.hover>{{ __('Deposits') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-up-tray" :href="route('admin.withdrawals')" :current="request()->routeIs('admin.withdrawals')" :badge="$pending['withdrawals'] ?: null" wire:navigate.hover>{{ __('Withdrawals') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('admin.kyc')" :current="request()->routeIs('admin.kyc')" :badge="$pending['kyc'] ?: null" wire:navigate.hover>{{ __('Verifications') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('admin.tickets')" :current="request()->routeIs('admin.tickets*')" :badge="$pending['tickets'] ?: null" wire:navigate.hover>{{ __('Support') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group :heading="__('Manage')" class="grid">
                        <flux:sidebar.item icon="sparkles" :href="route('admin.plans')" :current="request()->routeIs('admin.plans')" wire:navigate.hover>{{ __('Plans') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-pie" :href="route('admin.investments')" :current="request()->routeIs('admin.investments')" wire:navigate.hover>{{ __('Investments') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="wallet" :href="route('admin.wallets')" :current="request()->routeIs('admin.wallets')" wire:navigate.hover>{{ __('Deposit wallets') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="megaphone" :href="route('admin.announcements')" :current="request()->routeIs('admin.announcements')" wire:navigate.hover>{{ __('Announcements') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate.hover>{{ __('Site settings') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    @php($unread = auth()->user()->unreadNotifications()->count())
                    <flux:sidebar.group :heading="__('My account')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate.hover>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="sparkles" :href="route('invest')" :current="request()->routeIs('invest')" wire:navigate.hover>{{ __('Plans') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-pie" :href="route('investments')" :current="request()->routeIs('investments')" wire:navigate.hover>{{ __('My investments') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('deposit')" :current="request()->routeIs('deposit')" wire:navigate.hover>{{ __('Deposit') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-up-tray" :href="route('withdraw')" :current="request()->routeIs('withdraw')" wire:navigate.hover>{{ __('Withdraw') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('transactions')" :current="request()->routeIs('transactions')" wire:navigate.hover>{{ __('Transactions') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                    <flux:sidebar.group :heading="__('More')" class="grid">
                        <flux:sidebar.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" :badge="$unread ?: null" wire:navigate.hover>{{ __('Notifications') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('kyc')" :current="request()->routeIs('kyc')" wire:navigate.hover>{{ __('Verification') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="gift" :href="route('referrals')" :current="request()->routeIs('referrals')" wire:navigate.hover>{{ __('Referrals') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chat-bubble-left-right" :href="route('support')" :current="request()->routeIs('support*')" wire:navigate.hover>{{ __('Support') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="globe-alt" :href="route('home')" wire:navigate data-view-website>{{ __('View website') }}</flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:radio.group x-data variant="segmented" size="sm" x-model="$flux.appearance" :label="__('Appearance')" class="in-data-flux-sidebar-collapsed-desktop:hidden" data-appearance-switch>
                <flux:radio value="light" icon="sun" :aria-label="__('Light')" />
                <flux:radio value="dark" icon="moon" :aria-label="__('Dark')" />
                <flux:radio value="system" icon="computer-desktop" :aria-label="__('System')" />
            </flux:radio.group>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="sticky top-0 z-10 gap-1 border-b border-zinc-200 bg-white/90 backdrop-blur-lg lg:hidden dark:border-white/10 dark:bg-ink-950/90">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-3" inset="left" :aria-label="__('Open menu')" />

            <a href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}" class="ms-1 flex items-center gap-2" wire:navigate>
                <x-app-logo-icon class="size-7" />
                <span class="font-display font-bold text-ink-900 dark:text-white">{{ \App\Models\Setting::get('site_name') }}</span>
            </a>

            <flux:spacer />

            <x-theme-toggle />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('home')" icon="globe-alt" wire:navigate>
                            {{ __('View website') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main class="!p-0">
            <div class="p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </div>
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
