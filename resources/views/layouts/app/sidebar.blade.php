<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-navy-950">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-white/10 dark:bg-navy-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('My account')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate.hover>{{ __('Dashboard') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="sparkles" :href="route('invest')" :current="request()->routeIs('invest')" wire:navigate.hover>{{ __('Plans') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="chart-pie" :href="route('investments')" :current="request()->routeIs('investments')" wire:navigate.hover>{{ __('My investments') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-down-tray" :href="route('deposit')" :current="request()->routeIs('deposit')" wire:navigate.hover>{{ __('Deposit') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="arrow-up-tray" :href="route('withdraw')" :current="request()->routeIs('withdraw')" wire:navigate.hover>{{ __('Withdraw') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('transactions')" :current="request()->routeIs('transactions')" wire:navigate.hover>{{ __('Transactions') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="identification" :href="route('kyc')" :current="request()->routeIs('kyc')" wire:navigate.hover>{{ __('Verification') }}</flux:sidebar.item>
                    <flux:sidebar.item icon="gift" :href="route('referrals')" :current="request()->routeIs('referrals')" wire:navigate.hover>{{ __('Referrals') }}</flux:sidebar.item>
                </flux:sidebar.group>

                @if (auth()->user()->is_admin)
                    @php($pending = \App\Support\AdminCounts::pending())
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate.hover>{{ __('Overview') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users*')" wire:navigate.hover>{{ __('Users') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="sparkles" :href="route('admin.plans')" :current="request()->routeIs('admin.plans')" wire:navigate.hover>{{ __('Plans') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('admin.deposits')" :current="request()->routeIs('admin.deposits')" :badge="$pending['deposits'] ?: null" wire:navigate.hover>{{ __('Deposits') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-up-tray" :href="route('admin.withdrawals')" :current="request()->routeIs('admin.withdrawals')" :badge="$pending['withdrawals'] ?: null" wire:navigate.hover>{{ __('Withdrawals') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-pie" :href="route('admin.investments')" :current="request()->routeIs('admin.investments')" wire:navigate.hover>{{ __('Investments') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('admin.kyc')" :current="request()->routeIs('admin.kyc')" :badge="$pending['kyc'] ?: null" wire:navigate.hover>{{ __('Verifications') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="megaphone" :href="route('admin.announcements')" :current="request()->routeIs('admin.announcements')" wire:navigate.hover>{{ __('Announcements') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="document-magnifying-glass" :href="route('admin.ledger')" :current="request()->routeIs('admin.ledger')" wire:navigate.hover>{{ __('Audit log') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate.hover>{{ __('Site settings') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="rounded-lg border border-gold-300/60 bg-gold-50 p-3 text-xs text-gold-900 in-data-flux-sidebar-collapsed-desktop:hidden dark:border-gold-400/30 dark:bg-gold-400/10 dark:text-gold-100">
                <p class="font-semibold">{{ __('Demo platform') }}</p>
                <p class="mt-0.5">{{ __('No real funds. Everything here is simulated.') }}</p>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

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
            <x-demo-banner class="sticky top-0 z-10" />
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
