@props(['category'])
{{--
    The live price of the asset a plan category tracks, quoted in USDT like the
    plans themselves, e.g. "BTC/USDT 77,196.00 +1.20%". The data-price /
    data-change hooks let resources/js/site.js refresh it every minute.
--}}
@php
    $key = $category->priceKey();
    $prices = $key ? app(\App\Services\MarketPrices::class)->cached() : null;
    $cents = $key ? $prices[$key.'_cents'] : null;
    $change = $key ? $prices[$key.'_change'] : null;
@endphp

@if ($category === \App\Enums\PlanCategory::Usdt)
    <span {{ $attributes->class('inline-flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400') }}>
        <span class="font-semibold" style="color: {{ $category->color() }}">USDT</span>
        <span class="tabular-nums">1.00</span>
        <span>· {{ __('stable') }}</span>
    </span>
@elseif ($key)
    <span {{ $attributes->class('inline-flex flex-wrap items-center gap-x-1.5 text-xs text-zinc-500 dark:text-zinc-400') }} data-coin-price="{{ $key }}" title="{{ __('Live market price, refreshed every minute') }}">
        <span class="font-semibold" style="color: {{ $category->color() }}">{{ $category->pair() }}</span>
        <span data-price="{{ $key }}" data-price-format="usdt" class="rounded px-0.5 font-medium text-zinc-900 tabular-nums dark:text-white">{{ $cents !== null ? \App\Support\Usdt::format($cents * 10_000) : '—' }}</span>
        <span data-change="{{ $key }}" @class(['tabular-nums', 'text-green-600 dark:text-green-400' => $change !== null && $change >= 0, 'text-red-600 dark:text-red-400' => $change !== null && $change < 0])>
            @if ($change !== null)
                {{ $change > 0 ? '+' : '' }}{{ number_format($change, 2) }}%
            @endif
        </span>
    </span>
@endif
