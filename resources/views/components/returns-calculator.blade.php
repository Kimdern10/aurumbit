@props(['plans', 'btcPriceCents' => null, 'goldPriceCents' => null, 'dark' => false])
{{--
    Shows what a user would earn: pick a plan, type an amount, see the daily
    earning, the profit at the end of the term, the total payout and the date.
    Worked out in the browser with whole satoshis (BigInt), like the server.
--}}
@php
    $prices = app(\App\Services\MarketPrices::class)->cached();
    $btcPriceCents ??= $prices['btc_cents'];
    $goldPriceCents ??= $prices['gold_cents'];
    $data = $plans->map(fn ($plan) => [
        'id' => $plan->id,
        'name' => $plan->name,
        'category' => $plan->category->label(),
        'gold' => $plan->category === \App\Enums\PlanCategory::Gold,
        'days' => $plan->duration_days,
        'bps' => $plan->target_return_bps,
        'rate' => $plan->targetPercent(),
        'daily' => $plan->dailyPercent(),
        'min' => \App\Support\Btc::format($plan->min_sats),
        'max' => $plan->max_sats ? \App\Support\Btc::format($plan->max_sats) : null,
        'minSats' => $plan->min_sats,
        'maxSats' => $plan->max_sats,
    ])->values();
    $first = $plans->first();
@endphp

@if ($first)
    <div wire:ignore data-calculator data-plans='@json($data)' data-btc-price="{{ $btcPriceCents }}" data-gold-price="{{ $goldPriceCents }}"
        {{ $attributes->class(['rounded-2xl border p-5 sm:p-6', 'border-white/10 bg-white/5 text-white' => $dark, 'border-zinc-200 bg-white dark:border-white/10 dark:bg-navy-900' => ! $dark]) }}>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span @class(['text-sm font-medium', 'text-zinc-300' => $dark, 'text-zinc-700 dark:text-zinc-300' => ! $dark])>{{ __('Plan') }}</span>
                <select data-calc-plan class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 dark:border-white/15 dark:bg-navy-950 dark:text-white">
                    @foreach ($data as $plan)
                        <option value="{{ $plan['id'] }}">{{ $plan['category'] }}: {{ $plan['name'] }} · {{ trans_choice(':count day|:count days', $plan['days']) }} · {{ $plan['rate'] }}%</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span @class(['text-sm font-medium', 'text-zinc-300' => $dark, 'text-zinc-700 dark:text-zinc-300' => ! $dark])>{{ __('Amount (BTC)') }}</span>
                <input data-calc-amount type="text" inputmode="decimal" value="{{ \App\Support\Btc::format(max($first->min_sats, 1_000_000)) }}" autocomplete="off"
                    class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 tabular-nums dark:border-white/15 dark:bg-navy-950 dark:text-white">
                <span data-calc-limits @class(['mt-1 block text-xs', 'text-zinc-400' => $dark, 'text-zinc-500' => ! $dark])></span>
            </label>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                'rate' => __('Rate'),
                'daily' => __('Earned per day'),
                'profit' => __('Profit at the end'),
                'total' => __('You receive'),
            ] as $key => $label)
                <div @class(['rounded-xl p-3', 'bg-white/5' => $dark, 'bg-zinc-50 dark:bg-white/5' => ! $dark, 'ring-1 ring-gold-400/60' => $key === 'total'])>
                    <dt @class(['text-xs', 'text-zinc-400' => $dark, 'text-zinc-500 dark:text-zinc-400' => ! $dark])>{{ $label }}</dt>
                    <dd data-calc-{{ $key }} @class(['mt-1 font-display text-lg font-semibold tabular-nums', 'text-gold-300' => $key === 'total' && $dark, 'text-gold-700 dark:text-gold-300' => $key === 'total' && ! $dark])>—</dd>
                    <dd data-calc-{{ $key }}-sub @class(['text-[11px]', 'text-zinc-400' => $dark, 'text-zinc-500' => ! $dark])></dd>
                </div>
            @endforeach
        </dl>

        <p data-calc-summary @class(['mt-4 text-sm', 'text-zinc-300' => $dark, 'text-zinc-600 dark:text-zinc-300' => ! $dark])></p>
        <p @class(['mt-2 text-[11px]', 'text-zinc-400' => $dark, 'text-zinc-500' => ! $dark])>{{ __('Figures use the plan rate and are paid with your capital at the end of the term. Investing carries risk; see the risk disclosure.') }}</p>
    </div>
@endif
