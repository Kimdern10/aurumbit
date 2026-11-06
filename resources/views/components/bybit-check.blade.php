@props(['check', 'detailed' => false])
{{--
    What Bybit received for a customer's deposit, from BybitAccount::checkDeposit():
    green when the coin and amount match, amber when they differ, grey when Bybit
    has no deposit with that transaction ID. Shows nothing without a Bybit key.
--}}
@if ($check)
    @php($bybit = $check['bybit'])
    <div {{ $attributes->class('text-xs') }} data-bybit-check="{{ $check['state'] }}">
        @if ($check['state'] === 'match')
            <flux:badge size="sm" color="green" icon="check-badge">{{ __('Bybit received :amount', ['amount' => $bybit['amount'].' '.$bybit['coin']]) }}</flux:badge>
            @if ($detailed)
                <p class="mt-1 text-green-700 dark:text-green-400">{{ __('Bybit confirms it received exactly this amount on :chain. Safe to credit.', ['chain' => $bybit['chain']]) }}</p>
            @endif
        @elseif ($check['state'] === 'different')
            <flux:badge size="sm" color="amber" icon="exclamation-triangle">{{ __('Bybit received :amount', ['amount' => $bybit['amount'].' '.$bybit['coin']]) }}{{ $bybit['credited'] ? '' : ' · '.__('not credited yet') }}</flux:badge>
            @if ($detailed)
                <p class="mt-1 text-amber-700 dark:text-amber-400">{{ __('This is not what the customer entered. The amount below uses what Bybit actually received. Check before crediting.') }}</p>
            @endif
        @else
            <flux:badge size="sm" color="zinc" icon="question-mark-circle">{{ __('Not received by Bybit') }}</flux:badge>
            @if ($detailed)
                <p class="mt-1 text-zinc-500">{{ __('Bybit has no deposit with this transaction ID in the last 30 days. It may still be confirming, or it was sent elsewhere. Do not credit it until it shows up.') }}</p>
            @endif
        @endif
    </div>
@endif
