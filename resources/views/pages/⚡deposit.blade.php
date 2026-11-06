<?php

use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\DepositWallet;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\MarketPrices;
use App\Services\WalletService;
use App\Support\Usdt;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Deposit')] class extends Component {
    use WithPagination;

    public ?int $walletId = null;

    public string $amount = '';

    public string $txid = '';

    public function mount(): void
    {
        $this->walletId = $this->wallets->first()?->id;
    }

    /**
     * @return Collection<int, DepositWallet>
     */
    #[Computed]
    public function wallets(): Collection
    {
        return DepositWallet::query()->available()->get();
    }

    #[Computed]
    public function wallet(): ?DepositWallet
    {
        return $this->wallets->firstWhere('id', $this->walletId);
    }

    /**
     * What the typed amount is worth in USDT today, when the coin has a live price.
     */
    #[Computed]
    public function estimate(): ?int
    {
        if ($this->wallet === null) {
            return null;
        }

        $priceCents = DepositWallet::priceCentsFor($this->wallet->coin, app(MarketPrices::class));

        return $priceCents === null ? null : Usdt::fromCoin($this->amount, $priceCents);
    }

    /**
     * @return LengthAwarePaginator<int, Deposit>
     */
    #[Computed]
    public function deposits(): LengthAwarePaginator
    {
        return Auth::user()->deposits()->with('wallet')->latest('id')->paginate(8);
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->available()->get();
    }

    public function save(WalletService $wallet): void
    {
        $this->txid = trim($this->txid);

        $this->validate([
            'walletId' => ['required', Rule::exists('deposit_wallets', 'id')->where('is_active', true)],
            'amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,8})?$/'],
            'txid' => ['required', 'string', 'min:20', 'max:120', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('deposits', 'reference')],
        ], [
            'walletId.required' => __('Choose the wallet you sent to.'),
            'amount.regex' => __('Enter the amount you sent, with up to 8 decimal places.'),
            'txid.regex' => __('The transaction ID only has letters and numbers. Copy it from your wallet.'),
            'txid.min' => __('The transaction ID looks too short. Copy the full ID from your wallet.'),
            'txid.unique' => __('This transaction has already been submitted.'),
        ]);

        try {
            $wallet->requestDeposit(Auth::user(), $this->wallet, $this->amount, $this->txid);
        } catch (WalletException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->reset('amount', 'txid');
        $this->resetPage();
        Flux::toast(variant: 'success', text: __('Deposit submitted. Its USDT value is credited once the transaction is confirmed on the blockchain.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Deposit') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Your balance is kept in USDT. Send any coin we accept to its wallet below; we credit its value in USDT once it is confirmed.') }}</flux:text>
    </div>

    @if ($this->wallets->isEmpty())
        <flux:callout color="amber" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Deposits are not open yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Please check back soon or contact support at :email.', ['email' => Setting::get('support_email')]) }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            <flux:card class="space-y-4 lg:col-span-2">
                <flux:radio.group wire:model.live="walletId" :label="__('1. Choose what to send')" variant="cards" class="flex-col">
                    @foreach ($this->wallets as $option)
                        <flux:radio :value="$option->id" :label="$option->coin" :description="__(':network network', ['network' => $option->network])" />
                    @endforeach
                </flux:radio.group>

                @if ($this->wallet)
                    <div class="space-y-3" wire:key="wallet-{{ $this->wallet->id }}">
                        <flux:heading>{{ __('2. Send :coin to this address', ['coin' => $this->wallet->coin]) }}</flux:heading>
                        @if ($qr = $this->wallet->qrUrl())
                            <flux:modal.trigger name="qr-zoom">
                                <button type="button" class="group relative mx-auto block cursor-zoom-in" aria-label="{{ __('Zoom in on the QR code') }}">
                                    <img src="{{ $qr }}" alt="{{ __('QR code for our :coin address', ['coin' => $this->wallet->coin]) }}" class="size-44 rounded-xl border border-zinc-200 bg-white object-contain p-2 transition group-hover:shadow-lg dark:border-white/10" data-deposit-qr>
                                    <span class="absolute right-2 bottom-2 flex size-8 items-center justify-center rounded-full bg-ink-950/80 text-white shadow"><flux:icon.magnifying-glass-plus variant="mini" /></span>
                                </button>
                            </flux:modal.trigger>
                            <flux:text class="text-center text-xs">{{ __('Tap the QR code to zoom in. Scan it with your wallet app, or copy the address below.') }}</flux:text>

                            <flux:modal name="qr-zoom" class="w-full max-w-lg" data-qr-zoom>
                                <div class="space-y-4">
                                    <flux:heading size="lg">{{ __('Send :coin on :network', ['coin' => $this->wallet->coin, 'network' => $this->wallet->network]) }}</flux:heading>
                                    <img src="{{ $qr }}" alt="{{ __('QR code for our :coin address', ['coin' => $this->wallet->coin]) }}" class="mx-auto aspect-square w-full max-w-sm rounded-2xl border border-zinc-200 bg-white object-contain p-3">
                                    <code class="block rounded-lg bg-zinc-100 px-3 py-2 text-center font-mono text-sm break-all text-zinc-900 dark:bg-white/10 dark:text-white">{{ $this->wallet->address }}</code>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <flux:button icon="clipboard-document" data-copy="{{ $this->wallet->address }}"><span data-copy-label>{{ __('Copy address') }}</span></flux:button>
                                        <flux:button icon="arrow-top-right-on-square" :href="$qr" target="_blank">{{ __('Open full size') }}</flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        @endif
                        <code class="block rounded-lg bg-zinc-100 px-3 py-3 font-mono text-sm break-all text-zinc-900 dark:bg-white/10 dark:text-white" data-deposit-address>{{ $this->wallet->address }}</code>
                        <flux:button class="w-full" icon="clipboard-document" data-copy="{{ $this->wallet->address }}"><span data-copy-label>{{ __('Copy address') }}</span></flux:button>
                        <ul class="list-disc space-y-1 pl-5 text-xs text-zinc-500 dark:text-zinc-400">
                            <li>{{ __('Only send :coin on the :network network. Other coins or networks will be lost.', ['coin' => $this->wallet->coin, 'network' => $this->wallet->network]) }}</li>
                            <li>{{ __('Check the first and last characters of the address after pasting it.') }}</li>
                            <li>{{ __('Minimum deposit: :min USDT worth.', ['min' => Usdt::format(Setting::integer('min_deposit_micros'))]) }}</li>
                        </ul>
                    </div>
                @endif
            </flux:card>

            <flux:card class="lg:col-span-3">
                <form wire:submit="save" class="space-y-5">
                    <flux:heading>{{ __('3. Tell us about your deposit') }}</flux:heading>
                    <flux:input wire:model.live.debounce.400ms="amount" :label="__('Amount sent (:coin)', ['coin' => $this->wallet?->coin ?? ''])" inputmode="decimal" autocomplete="off" placeholder="0.00" />
                    @if ($this->estimate)
                        <flux:text class="-mt-3 text-sm">{{ __('About :usdt USDT at today\'s price. The admin confirms the final amount.', ['usdt' => Usdt::format($this->estimate)]) }}</flux:text>
                    @endif
                    <flux:input wire:model="txid" :label="__('Transaction ID (TXID / hash)')" class="font-mono" autocomplete="off" spellcheck="false"
                        :description="__('Find it in your wallet\'s transaction details.')" />
                    <flux:button type="submit" variant="primary">{{ __('Submit deposit') }}</flux:button>
                </form>
            </flux:card>
        </div>
    @endif

    <x-coin-converter />

    <div>
        <flux:heading>{{ __('How much will I earn?') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Choose a plan and an amount to see your earnings and when you are paid.') }}</flux:text>
        <x-returns-calculator class="mt-3" :plans="$this->plans" />
    </div>

    @island
    <div wire:poll.20s data-live-history>
    <flux:card class="!p-0 overflow-hidden">
        <div class="px-4 pt-4"><flux:heading>{{ __('Your deposits') }}</flux:heading></div>
        <flux:table :paginate="$this->deposits" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Sent') }}</flux:table.column>
                <flux:table.column>{{ __('Credited') }}</flux:table.column>
                <flux:table.column>{{ __('Transaction') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->deposits as $deposit)
                    <flux:table.row :key="$deposit->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $deposit->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $deposit->coinLabel() }} <span class="text-xs text-zinc-500">{{ $deposit->network }}</span><a href="{{ $deposit->receiptUrl() }}" class="block font-mono text-[11px] text-brand-600 underline">{{ $deposit->code() }}</a></flux:table.cell>
                        <flux:table.cell>
                            @if ($deposit->credited_micros !== null)
                                <x-usdt :micros="$deposit->credited_micros" />
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            @if ($url = $deposit->explorerUrl())
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="underline">{{ Str::limit($deposit->reference, 12, '…') }}</a>
                            @else
                                {{ $deposit->reference ? Str::limit($deposit->reference, 12, '…') : '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$deposit->status->color()">{{ $deposit->status->label() }}</flux:badge>
                            @if ($deposit->admin_note) <p class="mt-1 max-w-56 truncate text-xs text-zinc-500">{{ $deposit->admin_note }}</p> @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">{{ __('No deposits yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
    </div>
    @endisland
</div>
