<?php

use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Services\BybitAccount;
use App\Services\MarketPrices;
use App\Support\Usdt;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The live balance of the company Bybit account and its latest incoming
 * deposits, each matched to the customer deposit that claims it.
 * Refreshes itself every minute.
 */
new class extends Component {
    /**
     * Coins in the Funding account with their USDT value where we have a price.
     *
     * @return list<array{coin: string, amount: string, usdt_micros: int|null}>|null
     */
    #[Computed]
    public function balances(): ?array
    {
        $balances = app(BybitAccount::class)->fundingBalances();

        if ($balances === null) {
            return null;
        }

        $prices = app(MarketPrices::class);

        return collect($balances)->map(function (string $amount, string $coin) use ($prices): array {
            $priceCents = DepositWallet::priceCentsFor($coin, $prices);
            $units = Usdt::fromCoin(rtrim(rtrim(number_format((float) $amount, 8, '.', ''), '0'), '.') ?: '0', $priceCents ?? 0);

            return ['coin' => $coin, 'amount' => $amount, 'usdt_micros' => $priceCents ? $units : null];
        })->sortByDesc(fn (array $row): int => $row['usdt_micros'] ?? -1)->values()->all();
    }

    /**
     * The latest Bybit deposits, each with the customer deposit that has the same transaction ID.
     *
     * @return Collection<int, array{deposit: array<string, mixed>, claim: Deposit|null}>
     */
    #[Computed]
    public function recentDeposits(): Collection
    {
        $deposits = collect(app(BybitAccount::class)->deposits() ?? [])->take(6);
        $claims = Deposit::query()->with('user:id,name')->whereIn('reference', $deposits->pluck('txid')->filter())->get()->keyBy(fn (Deposit $deposit): string => strtolower((string) $deposit->reference));

        return $deposits->map(fn (array $deposit): array => ['deposit' => $deposit, 'claim' => $claims[strtolower($deposit['txid'])] ?? null]);
    }

    public function refresh(): void
    {
        app(BybitAccount::class)->refresh();
        unset($this->balances, $this->recentDeposits);
    }
};
?>

<flux:card wire:poll.60s class="space-y-4" data-bybit-account>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <flux:heading class="flex items-center gap-2">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-green-500"></span></span>
                {{ __('Bybit account · live balance') }}
            </flux:heading>
            <flux:text class="text-xs">
                {{ __('Funding account, read with your read-only API key.') }}
                @if ($updated = app(\App\Services\BybitAccount::class)->updatedAt())
                    {{ __('Updated :time.', ['time' => \Illuminate\Support\Carbon::parse($updated)->diffForHumans()]) }}
                @endif
            </flux:text>
        </div>
        <flux:button size="sm" icon="arrow-path" wire:click="refresh" wire:loading.attr="disabled">{{ __('Refresh') }}</flux:button>
    </div>

    @if (! app(\App\Services\BybitAccount::class)->isConfigured())
        <flux:callout color="zinc" icon="key">
            <flux:callout.text>{{ __('Add BYBIT_API_KEY and BYBIT_API_SECRET to the .env file to see the live balance here.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($this->balances === null)
        <flux:callout color="amber" icon="exclamation-triangle">
            <flux:callout.text>{{ __('Bybit did not answer. Check the key is still valid (and its IP restriction), then press Refresh.') }}</flux:callout.text>
        </flux:callout>
    @else
        @php($totalMicros = collect($this->balances)->sum(fn (array $row): int => $row['usdt_micros'] ?? 0))
        <div>
            <p class="text-sm text-zinc-500">{{ __('Total (coins we have a price for)') }}</p>
            <p class="font-display text-2xl font-semibold [overflow-wrap:anywhere] text-zinc-900 dark:text-white" data-bybit-total><x-usdt :micros="$totalMicros" /></p>
        </div>

        <ul class="divide-y divide-zinc-100 text-sm dark:divide-white/10">
            @forelse ($this->balances as $row)
                <li class="flex items-center justify-between gap-3 py-2" wire:key="bybit-{{ $row['coin'] }}">
                    <span class="font-medium text-zinc-900 dark:text-white">{{ $row['coin'] }}</span>
                    <span class="min-w-0 text-end tabular-nums [overflow-wrap:anywhere]">
                        {{ rtrim(rtrim(number_format((float) $row['amount'], 8), '0'), '.') }} {{ $row['coin'] }}
                        <span class="block text-xs text-zinc-500">
                            @if ($row['usdt_micros'] !== null)
                                ≈ {{ \App\Support\Usdt::format($row['usdt_micros']) }} USDT
                            @else
                                {{ __('no price') }}
                            @endif
                        </span>
                    </span>
                </li>
            @empty
                <li class="py-2 text-zinc-500">{{ __('The Funding account is empty.') }}</li>
            @endforelse
        </ul>

        <div>
            <flux:heading size="sm">{{ __('Latest deposits received by Bybit') }}</flux:heading>
            <ul class="mt-2 divide-y divide-zinc-100 text-sm dark:divide-white/10">
                @forelse ($this->recentDeposits as $row)
                    @php($deposit = $row['deposit'])
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="bd-{{ $deposit['txid'] }}">
                        <div class="min-w-0">
                            <p class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ $deposit['amount'] }} {{ $deposit['coin'] }} <span class="text-xs font-normal text-zinc-500">{{ $deposit['chain'] }}</span></p>
                            <p class="truncate font-mono text-[11px] text-zinc-400" title="{{ $deposit['txid'] }}">{{ $deposit['txid'] ?: '—' }}</p>
                        </div>
                        @if ($row['claim'])
                            <a href="{{ $row['claim']->receiptUrl(true) }}" class="text-xs"><flux:badge size="sm" color="green">{{ __(':code · :name', ['code' => $row['claim']->code(), 'name' => $row['claim']->user->name]) }}</flux:badge></a>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('No customer has claimed this') }}</flux:badge>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">{{ __('No deposits in the last 30 days.') }}</li>
                @endforelse
            </ul>
        </div>
    @endif
</flux:card>
