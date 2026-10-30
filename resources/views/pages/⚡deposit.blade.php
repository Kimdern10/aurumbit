<?php

use App\Exceptions\WalletException;
use App\Models\Deposit;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\MarketPrices;
use App\Services\WalletService;
use App\Support\Btc;
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

    public string $amount = '';

    public string $txid = '';

    /**
     * @return LengthAwarePaginator<int, Deposit>
     */
    #[Computed]
    public function deposits(): LengthAwarePaginator
    {
        return Auth::user()->deposits()->latest('id')->paginate(8);
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
        abort_if(Setting::get('deposit_address') === '', 403);

        $this->txid = strtolower(trim($this->txid));

        $this->validate([
            'amount' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'txid' => ['required', 'regex:/^[0-9a-f]{64}$/', Rule::unique('deposits', 'reference')],
        ], [
            'amount.regex' => __('Enter an amount in BTC, with up to 8 decimal places.'),
            'txid.regex' => __('The transaction ID is 64 letters and numbers (0-9, a-f). Copy it from your wallet.'),
            'txid.unique' => __('This transaction has already been submitted.'),
        ]);

        try {
            $wallet->requestDeposit(Auth::user(), (int) Btc::toSats($this->amount), $this->txid);
        } catch (WalletException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->reset('amount', 'txid');
        $this->resetPage();
        Flux::toast(variant: 'success', text: __('Deposit submitted. It is credited once the transaction is confirmed on the blockchain.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Deposit') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Send Bitcoin to the address below, then tell us the amount and the transaction ID.') }}</flux:text>
    </div>

    @php($address = Setting::get('deposit_address'))

    @if ($address === '')
        <flux:callout color="amber" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Deposits are not open yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Please check back soon or contact support at :email.', ['email' => Setting::get('support_email')]) }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-6 lg:grid-cols-5">
            <flux:card class="space-y-4 lg:col-span-2">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-[#f7931a]/15 font-display font-bold text-[#f7931a]">₿</span>
                    <div>
                        <flux:heading>{{ __('Our BTC address') }}</flux:heading>
                        <flux:text class="text-xs">{{ __('Bitcoin network only') }}</flux:text>
                    </div>
                </div>
                <code class="block rounded-lg bg-zinc-100 px-3 py-3 font-mono text-sm break-all text-zinc-900 dark:bg-white/10 dark:text-white">{{ $address }}</code>
                <flux:button class="w-full" icon="clipboard-document" data-copy="{{ $address }}"><span data-copy-label>{{ __('Copy address') }}</span></flux:button>
                <ul class="list-disc space-y-1 pl-5 text-xs text-zinc-500 dark:text-zinc-400">
                    <li>{{ __('Only send BTC on the Bitcoin network. Other coins or networks will be lost.') }}</li>
                    <li>{{ __('Check the first and last characters of the address after pasting it.') }}</li>
                    <li>{{ __('Your balance is credited after the transaction is confirmed and checked.') }}</li>
                </ul>
            </flux:card>

            <flux:card class="lg:col-span-3">
                <form wire:submit="save" class="space-y-5">
                    <flux:heading>{{ __('I have sent a deposit') }}</flux:heading>
                    <flux:input wire:model="amount" :label="__('Amount sent (BTC)')" inputmode="decimal" autocomplete="off" placeholder="0.01"
                        :description="__('Minimum :min BTC', ['min' => Btc::format(Setting::integer('min_deposit_sats'))])" />
                    <flux:input wire:model="txid" :label="__('Transaction ID (TXID / hash)')" class="font-mono" autocomplete="off" spellcheck="false"
                        :description="__('Find it in your wallet\'s transaction details. It has 64 characters.')" />
                    <flux:button type="submit" variant="primary">{{ __('Submit deposit') }}</flux:button>
                </form>
            </flux:card>
        </div>
    @endif

    <div>
        <flux:heading>{{ __('How much will I earn?') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Choose a plan and an amount to see your earnings and when you are paid.') }}</flux:text>
        <x-returns-calculator class="mt-3" :plans="$this->plans" :btc-price-cents="app(MarketPrices::class)->cached()['btc_cents']" />
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <div class="px-4 pt-4"><flux:heading>{{ __('Your deposits') }}</flux:heading></div>
        <flux:table :paginate="$this->deposits" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Transaction') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->deposits as $deposit)
                    <flux:table.row :key="$deposit->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $deposit->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell><x-btc :sats="$deposit->amount_sats" /></flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            @if ($deposit->reference)
                                <a href="{{ Setting::get('explorer_tx_url').$deposit->reference }}" target="_blank" rel="noopener" class="underline">{{ Str::limit($deposit->reference, 12, '…') }}</a>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$deposit->status->color()">{{ $deposit->status->label() }}</flux:badge>
                            @if ($deposit->admin_note) <p class="mt-1 max-w-56 truncate text-xs text-zinc-500">{{ $deposit->admin_note }}</p> @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">{{ __('No deposits yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
