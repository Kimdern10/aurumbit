{{--
    Two-way converter between a coin and USDT at today's prices: type in either
    box and the other one updates. Worked out in the browser (resources/js/site.js)
    and for information only; deposits and withdrawals use the price at the time.
--}}
@php
    $prices = app(\App\Services\MarketPrices::class)->cached();
    $coins = collect([
        'BTC' => $prices['btc_cents'],
        'ETH' => $prices['eth_cents'],
        'SOL' => $prices['sol_cents'],
        'GOLD' => $prices['gold_cents'],
    ])->filter()->map(fn (int $cents): float => $cents / 100);
@endphp

@if ($coins->isNotEmpty())
    <div wire:ignore data-converter data-prices='@json($coins)' {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-ink-900') }}>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white"><flux:icon.arrows-right-left variant="mini" class="text-brand-600" /> {{ __('Converter') }}</p>
            <p class="text-xs text-zinc-500" data-converter-rate></p>
        </div>
        <div class="mt-4 grid items-end gap-3 sm:grid-cols-[1fr_auto_1fr]">
            <label class="block">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Coin') }}</span>
                <div class="mt-1.5 flex rounded-lg border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-brand-500 dark:border-white/15 dark:bg-ink-950">
                    <input data-converter-coin-amount type="text" inputmode="decimal" value="1" autocomplete="off" aria-label="{{ __('Coin amount') }}"
                        class="min-w-0 flex-1 rounded-s-lg bg-transparent px-3 py-2.5 text-sm text-zinc-900 tabular-nums outline-none dark:text-white">
                    <select data-converter-coin aria-label="{{ __('Coin') }}" class="rounded-e-lg border-s border-zinc-300 bg-zinc-50 px-2 text-sm font-medium text-zinc-900 outline-none dark:border-white/15 dark:bg-white/5 dark:text-white">
                        @foreach ($coins as $coin => $price)
                            <option value="{{ $coin }}">{{ $coin === 'GOLD' ? __('Gold (oz)') : $coin }}</option>
                        @endforeach
                    </select>
                </div>
            </label>
            <span class="hidden pb-2.5 text-zinc-400 sm:block">=</span>
            <label class="block">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">USDT</span>
                <input data-converter-usdt type="text" inputmode="decimal" autocomplete="off" aria-label="{{ __('USDT amount') }}"
                    class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 tabular-nums outline-none focus:ring-2 focus:ring-brand-500 dark:border-white/15 dark:bg-ink-950 dark:text-white">
            </label>
        </div>
        <p class="mt-3 text-[11px] text-zinc-500">{{ __('For information only. Deposits and withdrawals use the live price at the moment they are made.') }}</p>
    </div>
@endif
