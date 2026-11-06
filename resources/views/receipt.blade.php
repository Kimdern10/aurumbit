@php
    use App\Models\Setting;
    use App\Support\Btc;
    use App\Support\Usdt;

    $siteName = Setting::get('site_name');
    $receiptName = match ($kind) {
        'deposit' => __('Deposit receipt'),
        'withdrawal' => __('Withdrawal receipt'),
        'plan' => __('Plan receipt'),
    };
    $title = $receiptName.' '.$item->code();
    $statusTone = match ($item->status->value) {
        'approved', 'completed' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400',
        'rejected', 'cancelled' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400',
        default => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400',
    };
    $headlineLabel = $kind === 'withdrawal' ? __('You receive') : __('Amount');
    $note = $kind === 'plan' ? null : $item->admin_note;

    if ($kind === 'plan') {
        $returned = $item->status->value === 'active' ? null : $item->amount_micros + $item->earned_micros;
        $rows = [
            __('Reference') => $item->code(),
            __('Account') => $item->user->name.' · '.$item->user->email,
            __('Plan') => $item->plan->name,
            __('Amount invested') => Usdt::format($item->amount_micros).' USDT',
            __('Target rate') => __(':rate% over :days days (a target, not a guarantee)', ['rate' => rtrim(rtrim(number_format($item->target_return_bps / 100, 2), '0'), '.'), 'days' => $item->duration_days]),
            __('Started') => $item->starts_at->format('M j, Y g:i A'),
            __('Term ends') => $item->ends_at->format('M j, Y g:i A'),
            __('Profit paid') => $returned === null ? __(':amount USDT so far (paid at the end)', ['amount' => Usdt::format($item->earnedSoFar())]) : Usdt::format($item->earned_micros).' USDT',
            __('Total returned to balance') => $returned === null ? __('Not paid out yet') : Usdt::format($returned).' USDT',
            __('Closed') => $item->settled_at?->format('M j, Y g:i A') ?? __('Still running'),
        ];
        $headlineLabel = $returned === null ? __('Invested') : __('Returned to your balance');
        $headline = Usdt::format($returned ?? $item->amount_micros).' USDT';
        $explorer = null;
    } elseif ($kind === 'deposit') {
        $rows = [
            __('Coin sent') => $item->coinLabel(),
            __('Network') => $item->network ?? '—',
            __('Sent to our address') => $item->wallet?->address ?? '—',
            __('Blockchain transaction ID') => $item->reference ?? '—',
            __('USDT credited') => $item->credited_micros !== null ? Usdt::format($item->credited_micros).' USDT' : __('Not credited yet'),
        ];
        $headline = $item->credited_micros !== null ? Usdt::format($item->credited_micros).' USDT' : $item->coinLabel();
        $explorer = $item->explorerUrl();
    } else {
        $rows = [
            __('Taken from balance') => Usdt::format($item->amount_micros).' USDT',
            __('Paid as') => $item->payoutLabel(),
            __('Price used') => match (true) {
                $item->price_cents !== null => '1 '.$item->coin.' = '.Usdt::format($item->price_cents * 10_000).' USDT',
                $item->coin === 'USDT' => __('Not needed (paid in USDT)'),
                default => __('Requested before balances moved to USDT'),
            },
            __('Network') => $item->network->label(),
            __('Sent to address') => $item->address,
            __('Blockchain transaction ID') => $item->payout_txid ?? ($item->status->value === 'approved' ? __('Not provided') : '—'),
        ];
        $headline = $item->payoutLabel();
        $explorer = $item->payoutUrl();
    }

    if ($kind !== 'plan') {
        $rows = [
            __('Reference') => $item->code(),
            __('Requested') => $item->created_at->format('M j, Y g:i A'),
            __('Account') => $item->user->name.' · '.$item->user->email,
            ...$rows,
            __('Reviewed') => $item->reviewed_at?->format('M j, Y g:i A') ?? __('Waiting for review'),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
        <style>
            @media print {
                @page { margin: 16mm; }
                html, body { background: #fff !important; color: #000 !important; }
                html.dark { color-scheme: light; }
            }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-ink-600 antialiased print:bg-white dark:bg-ink-950 dark:text-ink-300">
        <div class="mx-auto w-full max-w-2xl px-4 py-8 sm:py-12 print:max-w-none print:p-0">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <a href="{{ $backUrl }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-900 hover:text-brand-600 dark:text-white">
                    <flux:icon.arrow-left variant="micro" /> {{ $kind === 'plan' ? __('Back to investments') : __('Back to transactions') }}
                </a>
                <flux:button variant="primary" icon="printer" onclick="window.print()" data-print-receipt>{{ __('Print / Save as PDF') }}</flux:button>
            </div>

            <article class="overflow-hidden rounded-3xl border border-ink-100 bg-white shadow-xl shadow-brand-900/5 print:rounded-none print:border-0 print:shadow-none dark:border-white/10 dark:bg-ink-900 print:dark:bg-white" data-receipt="{{ $item->code() }}">
                <header class="flex flex-wrap items-start justify-between gap-4 border-b border-ink-100 p-6 sm:p-8 dark:border-white/10 print:dark:border-ink-100">
                    <div class="flex items-center gap-2.5">
                        <x-app-logo-icon class="size-10" />
                        <div>
                            <p class="font-display text-lg font-bold text-ink-950 dark:text-white print:dark:text-ink-950">{{ $siteName }}</p>
                            <p class="text-xs text-ink-400">{{ Setting::get('support_email') }}</p>
                        </div>
                    </div>
                    <div class="text-end">
                        <p class="text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase">{{ $receiptName }}</p>
                        <p class="mt-1 font-mono text-sm text-ink-900 dark:text-white print:dark:text-ink-900">{{ $item->code() }}</p>
                    </div>
                </header>

                <div class="p-6 sm:p-8">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm text-ink-500">{{ $headlineLabel }}</p>
                            <p class="mt-1 font-display text-3xl font-extrabold [overflow-wrap:anywhere] text-ink-950 sm:text-4xl dark:text-white print:dark:text-ink-950">{{ $headline }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $statusTone }}">{{ $item->status->label() }}</span>
                    </div>

                    <dl class="mt-8 divide-y divide-ink-100 text-sm dark:divide-white/10 print:dark:divide-ink-100">
                        @foreach ($rows as $label => $value)
                            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                                <dt class="text-ink-500">{{ $label }}</dt>
                                <dd class="font-medium [overflow-wrap:anywhere] text-ink-900 sm:col-span-2 dark:text-white print:dark:text-ink-900 @if (str_contains($label, 'address') || str_contains($label, 'ID')) font-mono text-xs @endif">{{ $value }}</dd>
                            </div>
                        @endforeach
                        @if ($balanceAfter !== null)
                            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                                <dt class="text-ink-500">{{ __('Balance after') }}</dt>
                                <dd class="font-medium text-ink-900 sm:col-span-2 dark:text-white print:dark:text-ink-900">{{ Usdt::format($balanceAfter) }} USDT</dd>
                            </div>
                        @endif
                        @if ($note)
                            <div class="grid gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                                <dt class="text-ink-500">{{ __('Note') }}</dt>
                                <dd class="text-ink-900 sm:col-span-2 dark:text-white print:dark:text-ink-900">{{ $note }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($explorer)
                        <a href="{{ $explorer }}" target="_blank" rel="noopener" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 underline print:hidden dark:text-brand-400">
                            {{ __('View on the blockchain') }} <flux:icon.arrow-top-right-on-square variant="micro" />
                        </a>
                    @endif
                </div>

                <footer class="border-t border-ink-100 bg-ink-50/60 px-6 py-4 text-xs leading-relaxed text-ink-400 sm:px-8 dark:border-white/10 dark:bg-white/5 print:bg-white print:dark:border-ink-100">
                    {{ __('Printed :date. Quote the reference :code if you contact support.', ['date' => now()->format('M j, Y g:i A'), 'code' => $item->code()]) }}
                    @if (filled(Setting::get('company_details')))
                        <br>{{ Setting::get('company_details') }}
                    @endif
                </footer>
            </article>
        </div>

        @fluxScripts
    </body>
</html>
