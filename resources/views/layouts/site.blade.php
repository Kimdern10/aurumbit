@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
    <head>
        @include('partials.head', ['title' => $title, 'description' => $description])
    </head>
    <body class="min-h-screen bg-white font-sans text-zinc-700 antialiased dark:bg-navy-950 dark:text-zinc-300">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-navy-900">{{ __('Skip to content') }}</a>

        <div class="sticky top-0 z-40">
            <x-demo-banner />

            <header data-site-header class="border-b border-transparent bg-white/70 backdrop-blur-lg transition-colors data-scrolled:border-zinc-200 data-scrolled:bg-white/90 dark:bg-navy-950/60 dark:data-scrolled:border-white/10 dark:data-scrolled:bg-navy-950/90">
                <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="{{ __('Main') }}">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="{{ \App\Models\Setting::get('site_name') }} {{ __('home') }}">
                        <x-app-logo-icon class="size-9" />
                        <span class="font-display text-lg font-bold text-navy-900 dark:text-white">{{ \App\Models\Setting::get('site_name') }}</span>
                    </a>

                    <div class="hidden items-center gap-7 text-sm font-medium md:flex">
                        <a href="{{ route('home') }}#how-it-works" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('How it works') }}</a>
                        <a href="{{ route('home') }}#plans" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('Plans') }}</a>
                        <a href="{{ route('home') }}#security" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('Security') }}</a>
                        <a href="{{ route('home') }}#faq" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('FAQ') }}</a>
                    </div>

                    <div class="flex items-center gap-2">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" size="sm" icon-trailing="arrow-right">{{ __('Dashboard') }}</flux:button>
                        @else
                            <span class="hidden sm:block"><flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button></span>
                            <flux:button :href="route('register')" variant="primary" size="sm"><span class="sm:hidden">{{ __('Sign up') }}</span><span class="hidden sm:inline">{{ __('Open demo account') }}</span></flux:button>
                        @endauth

                        <details class="group relative md:hidden">
                            <summary class="flex size-9 cursor-pointer list-none items-center justify-center rounded-lg hover:bg-zinc-100 dark:hover:bg-white/10" aria-label="{{ __('Menu') }}">
                                <flux:icon.bars-3 class="size-5 group-open:hidden" />
                                <flux:icon.x-mark class="hidden size-5 group-open:block" />
                            </summary>
                            <div class="absolute right-0 mt-2 w-56 rounded-xl border border-zinc-200 bg-white p-2 shadow-xl dark:border-white/10 dark:bg-navy-900">
                                <a href="{{ route('home') }}#how-it-works" class="block rounded-lg px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">{{ __('How it works') }}</a>
                                <a href="{{ route('home') }}#plans" class="block rounded-lg px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">{{ __('Plans') }}</a>
                                <a href="{{ route('home') }}#security" class="block rounded-lg px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">{{ __('Security') }}</a>
                                <a href="{{ route('home') }}#faq" class="block rounded-lg px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">{{ __('FAQ') }}</a>
                                @guest
                                    <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 hover:bg-zinc-100 dark:hover:bg-white/10">{{ __('Log in') }}</a>
                                @endguest
                            </div>
                        </details>
                    </div>
                </nav>
            </header>
        </div>

        <main id="main">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-navy-950">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-10 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                            <x-app-logo-icon class="size-8" />
                            <span class="font-display text-lg font-bold text-navy-900 dark:text-white">{{ \App\Models\Setting::get('site_name') }}</span>
                        </a>
                        <p class="mt-3 max-w-md text-sm">{{ \App\Models\Setting::get('tagline') }}</p>
                        <div class="mt-5 rounded-xl border border-gold-300/60 bg-gold-50 p-4 text-sm text-gold-900 dark:border-gold-400/30 dark:bg-gold-400/10 dark:text-gold-100">
                            <p class="font-semibold">{{ __('Demo platform, no real funds') }}</p>
                            <p class="mt-1">{{ __('This website is a demonstration. No Bitcoin is received, held or sent, and every balance, deposit, earning and withdrawal you see is simulated.') }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-navy-900 dark:text-white">{{ __('Platform') }}</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('home') }}#plans" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('Plans') }}</a></li>
                            <li><a href="{{ route('home') }}#how-it-works" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('How it works') }}</a></li>
                            <li><a href="{{ route('home') }}#faq" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('FAQ') }}</a></li>
                            <li><a href="mailto:{{ \App\Models\Setting::get('support_email') }}" class="hover:text-gold-600 dark:hover:text-gold-300">{{ \App\Models\Setting::get('support_email') }}</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-navy-900 dark:text-white">{{ __('Legal') }}</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('legal.risk') }}" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('Risk disclosure') }}</a></li>
                            <li><a href="{{ route('legal.terms') }}" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('Terms of use') }}</a></li>
                            <li><a href="{{ route('legal.privacy') }}" class="hover:text-gold-600 dark:hover:text-gold-300">{{ __('Privacy policy') }}</a></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-10 space-y-2 border-t border-zinc-200 pt-6 text-xs leading-relaxed text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                    <p>{{ __('Risk warning: crypto-assets and commodity investments are high risk. Their value can go down as well as up and you may lose all the money you invest. Past performance and target returns are not a reliable indicator of future results. Returns are not guaranteed.') }}</p>
                    <p>{{ \App\Models\Setting::get('company_details') }}</p>
                    <p>&copy; {{ now()->year }} {{ \App\Models\Setting::get('site_name') }}.</p>
                </div>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
