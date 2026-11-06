@php
    use App\Support\Btc;

    $prices = app(\App\Services\MarketPrices::class)->cached();

    $assets = [
        'btc' => ['Bitcoin', '₿', '#f7931a'],
        'eth' => ['Ethereum', 'Ξ', '#627eea'],
        'gold' => ['Gold', 'Au', '#c9a227'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white font-sans text-ink-600 antialiased dark:bg-ink-950 dark:text-ink-300">
        <div class="grid min-h-svh lg:grid-cols-2">
            {{-- Brand panel --}}
            <aside class="relative isolate hidden flex-col justify-between overflow-hidden bg-ink-950 p-10 text-white lg:flex xl:p-14" data-prices-url="{{ route('prices') }}">
                <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(40rem_30rem_at_100%_0%,rgb(22_82_240/0.45),transparent),radial-gradient(30rem_24rem_at_0%_100%,rgb(6_182_212/0.22),transparent)]" aria-hidden="true"></div>

                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5" aria-label="{{ \App\Models\Setting::get('site_name') }} {{ __('home') }}">
                    <x-app-logo-icon class="size-9" />
                    <span class="font-display text-lg font-bold">{{ \App\Models\Setting::get('site_name') }}</span>
                </a>

                <div class="max-w-md">
                    <h2 class="font-display text-4xl leading-[1.1] font-extrabold xl:text-5xl">
                        {{ __('Grow your crypto with') }}
                        <span class="text-brand-gradient animate-shine">{{ __('fixed-term plans.') }}</span>
                    </h2>
                    <p class="mt-5 text-base leading-relaxed text-ink-300">{{ \App\Models\Setting::get('tagline') }}</p>

                    <ul class="mt-10 divide-y divide-white/10 rounded-2xl border border-white/10 bg-white/5 px-5 backdrop-blur">
                        @foreach ($assets as $asset => [$label, $symbol, $color])
                            <li class="flex items-center justify-between gap-3 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 items-center justify-center rounded-full font-display text-sm font-bold" style="background: {{ $color }}26; color: {{ $color }}">{{ $symbol }}</span>
                                    <span class="font-semibold">{{ __($label) }}</span>
                                </div>
                                <div class="text-right">
                                    <p data-price="{{ $asset }}" class="rounded px-1 font-semibold tabular-nums">{{ $prices[$asset.'_cents'] !== null ? Btc::usd($prices[$asset.'_cents']) : '—' }}</p>
                                    <p data-change="{{ $asset }}" class="text-xs font-medium text-ink-400">
                                        @if ($prices[$asset.'_change'] !== null)
                                            {{ $prices[$asset.'_change'] > 0 ? '+' : '' }}{{ number_format($prices[$asset.'_change'], 2) }}%
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink-300">
                    @foreach ([['shield-check', 'Two-step login'], ['lock-closed', 'Private ID storage'], ['chat-bubble-left-right', 'Live support']] as [$icon, $text])
                        <li class="flex items-center gap-2"><flux:icon :name="$icon" variant="mini" class="text-brand-400" /> {{ __($text) }}</li>
                    @endforeach
                </ul>
            </aside>

            {{-- Form --}}
            <main class="flex flex-col px-6 py-8 sm:px-10">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2.5 lg:invisible" aria-label="{{ \App\Models\Setting::get('site_name') }} {{ __('home') }}">
                        <x-app-logo-icon class="size-9" />
                        <span class="font-display text-lg font-bold text-ink-900 dark:text-white">{{ \App\Models\Setting::get('site_name') }}</span>
                    </a>

                    <div class="flex items-center gap-1">
                        <x-theme-toggle />
                        <a href="{{ route('home') }}" wire:navigate aria-label="{{ __('Back to home') }}" class="inline-flex h-10 items-center gap-1.5 whitespace-nowrap rounded-full px-3 text-sm font-semibold text-ink-900 hover:bg-ink-50 sm:px-4 dark:text-white dark:hover:bg-white/10">
                            <flux:icon.arrow-left variant="micro" /> <span class="hidden min-[400px]:inline">{{ __('Back to home') }}</span>
                        </a>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-center py-12">
                    <div data-auth-form class="flex w-full max-w-[26rem] flex-col gap-7">
                        {{ $slot }}
                    </div>
                </div>

                <div class="flex flex-col items-center justify-between gap-3 border-t border-ink-100 pt-6 text-xs text-ink-400 sm:flex-row dark:border-white/10">
                    <p class="flex items-center gap-1.5">
                        <flux:icon.lock-closed variant="micro" class="text-ink-300 dark:text-ink-500" />
                        {{ __('Secured with end-to-end encryption') }}
                    </p>
                    <div class="flex items-center gap-4">
                        <a href="{{ route('legal.terms') }}" wire:navigate class="hover:text-ink-700 dark:hover:text-white">{{ __('Terms') }}</a>
                        <a href="{{ route('legal.privacy') }}" wire:navigate class="hover:text-ink-700 dark:hover:text-white">{{ __('Privacy') }}</a>
                        <span>&copy; {{ now()->year }} {{ \App\Models\Setting::get('site_name') }}</span>
                    </div>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
