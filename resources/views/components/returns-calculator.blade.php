@props(['plans', 'goldPriceCents' => null, 'dark' => false])
{{--
    Shows what a user would earn: pick a plan, type an amount, see the daily
    earning, the profit at the end of the term, the total payout and the date.
    Worked out in the browser with whole micro-USDT (BigInt), like the server.
    Coin plans also show what the USDT figures are worth in that coin at the
    live price, which resources/js/site.js refreshes every minute.
--}}
@php
    $prices = app(\App\Services\MarketPrices::class)->cached();
    $goldPriceCents ??= $prices['gold_cents'];
    $livePrices = collect(['btc', 'eth', 'sol', 'gold'])->mapWithKeys(fn (string $key): array => [$key => $prices[$key.'_cents']])->filter();
    $data = $plans->map(fn ($plan) => [
        'id' => $plan->id,
        'name' => $plan->name,
        'category' => $plan->category->label(),
        'gold' => $plan->category === \App\Enums\PlanCategory::Gold,
        'priceKey' => $plan->category->priceKey(),
        'ticker' => $plan->category === \App\Enums\PlanCategory::Gold ? 'oz of gold' : $plan->category->ticker(),
        'mix' => $plan->category === \App\Enums\PlanCategory::CryptoIndex,
        'days' => $plan->duration_days,
        'bps' => $plan->target_return_bps,
        'rate' => $plan->targetPercent(),
        'daily' => $plan->dailyPercent(),
        'min' => \App\Support\Usdt::format($plan->min_micros),
        'max' => $plan->max_micros ? \App\Support\Usdt::format($plan->max_micros) : null,
        'minMicros' => $plan->min_micros,
        'maxMicros' => $plan->max_micros,
    ])->values();
    $first = $plans->first();
@endphp

@if ($first)
    <div wire:ignore data-calculator data-plans='@json($data)' data-gold-price="{{ $goldPriceCents }}" data-live-prices='@json($livePrices)' data-prices-url="{{ route('prices') }}"
        {{ $attributes->class(['rounded-2xl border p-5 sm:p-6', 'border-white/10 bg-white/5 text-white' => $dark, 'border-zinc-200 bg-white dark:border-white/10 dark:bg-ink-900' => ! $dark]) }}>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span @class(['text-sm font-medium', 'text-zinc-300' => $dark, 'text-zinc-700 dark:text-zinc-300' => ! $dark])>{{ __('Plan') }}</span>
                <select data-calc-plan class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 dark:border-white/15 dark:bg-ink-950 dark:text-white">
                    @foreach ($data as $plan)
                        <option value="{{ $plan['id'] }}">{{ $plan['category'] }}: {{ $plan['name'] }} · {{ trans_choice(':count day|:count days', $plan['days']) }} · {{ $plan['rate'] }}%</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span @class(['text-sm font-medium', 'text-zinc-300' => $dark, 'text-zinc-700 dark:text-zinc-300' => ! $dark])>{{ __('Amount (USDT)') }}</span>
                <input data-calc-amount type="text" inputmode="decimal" value="{{ str_replace(',', '', \App\Support\Usdt::format(max($first->min_micros, 1_000_000_000))) }}" autocomplete="off"
                    class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 tabular-nums dark:border-white/15 dark:bg-ink-950 dark:text-white">
                <span data-calc-limits @class(['mt-1 block text-xs', 'text-zinc-400' => $dark, 'text-zinc-500' => ! $dark])></span>
            </label>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 sm:grid-cols-4">
            @foreach ([
                'rate' => __('Rate'),
                'daily' => __('Earned per day'),
                'profit' => __('Profit at the end'),
                'total' => __('You receive'),
            ] as $key => $label)
                <div @class(['min-w-0 rounded-xl p-3', 'bg-white/5' => $dark, 'bg-zinc-50 dark:bg-white/5' => ! $dark, 'ring-1 ring-brand-400/60' => $key === 'total'])>
                    <dt @class(['text-xs', 'text-zinc-400' => $dark, 'text-zinc-500 dark:text-zinc-400' => ! $dark])>{{ $label }}</dt>
                    <dd data-calc-{{ $key }} @class(['mt-1 font-display text-base font-semibold tabular-nums [overflow-wrap:anywhere] sm:text-lg', 'text-brand-300' => $key === 'total' && $dark, 'text-brand-700 dark:text-brand-300' => $key === 'total' && ! $dark])>—</dd>
                    <dd data-calc-{{ $key }}-sub @class(['text-[11px]', 'text-zinc-400' => $dark, 'text-zinc-500' => ! $dark])></dd>
                </div>
            @endforeach
        </dl>

        <p data-calc-summary @class(['mt-4 text-sm', 'text-zinc-300' => $dark, 'text-zinc-600 dark:text-zinc-300' => ! $dark])></p>
        <p @class(['mt-2 text-[11px]', 'text-zinc-400' => $dark, 'text-zinc-500' => ! $dark])>{{ __('Estimates based on the plan\'s target rate. They are not guaranteed: you could earn less or lose money. Capital and profit are paid together at the end of the term.') }} <a href="{{ route('legal.risk') }}" class="underline" wire:navigate>{{ __('Risk disclosure') }}</a></p>
    </div>
@endif
