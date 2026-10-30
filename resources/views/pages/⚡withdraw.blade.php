<?php

use App\Exceptions\WalletException;
use App\Models\Setting;
use App\Models\Withdrawal;
use App\Rules\BitcoinAddress;
use App\Services\WalletService;
use App\Support\Btc;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Withdraw')] class extends Component {
    use WithPagination;

    public string $amount = '';

    public string $address = '';

    public bool $confirmAddress = false;

    /**
     * @return LengthAwarePaginator<int, Withdrawal>
     */
    #[Computed]
    public function withdrawals(): LengthAwarePaginator
    {
        return Auth::user()->withdrawals()->latest('id')->paginate(8);
    }

    public function save(WalletService $wallet): void
    {
        $this->address = trim($this->address);

        $this->validate([
            'amount' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'address' => ['required', 'string', 'max:100', new BitcoinAddress],
            'confirmAddress' => ['accepted'],
        ], [
            'amount.regex' => __('Enter an amount in BTC, with up to 8 decimal places.'),
            'confirmAddress.accepted' => __('Please confirm the address is yours and correct.'),
        ]);

        $key = 'withdraw:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('amount', __('Too many requests. Please try again in a few minutes.'));

            return;
        }

        RateLimiter::hit($key, 600);

        try {
            $wallet->requestWithdrawal(Auth::user(), (int) Btc::toSats($this->amount), $this->address);
        } catch (WalletException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->reset('amount', 'address', 'confirmAddress');
        $this->resetPage();
        Flux::toast(variant: 'success', text: __('Withdrawal requested. The amount is on hold until an admin reviews it.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Withdraw') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Available balance:') }} <x-btc :sats="auth()->user()->balance_sats" class="font-semibold text-zinc-900 dark:text-white" /></flux:text>
    </div>

    @if (Setting::enabled('withdrawal_requires_kyc') && ! auth()->user()->isKycVerified())
        <flux:callout color="sky" icon="identification">
            <flux:callout.heading>{{ __('Identity verification needed') }}</flux:callout.heading>
            <flux:callout.text>{{ __('You need a verified identity before you can withdraw.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('kyc')" wire:navigate>{{ __('Go to verification') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <flux:card class="lg:col-span-3">
            <form wire:submit="save" class="space-y-5">
                <flux:heading>{{ __('Request a withdrawal') }}</flux:heading>
                <flux:input wire:model="amount" :label="__('Amount (BTC)')" inputmode="decimal" autocomplete="off" placeholder="0.005"
                    :description="__('Minimum :min BTC', ['min' => Btc::format(Setting::integer('min_withdrawal_sats'))])" />
                <flux:input wire:model.blur="address" :label="__('Your Bitcoin address')" autocomplete="off" spellcheck="false" class="font-mono"
                    placeholder="bc1q…" :description="__('Mainnet address starting with 1, 3, bc1q or bc1p. We check its checksum.')" />
                <flux:field variant="inline">
                    <flux:checkbox wire:model="confirmAddress" />
                    <flux:label class="text-sm leading-snug">{{ __('This address belongs to me and I have checked every character. Crypto sent to a wrong address cannot be recovered.') }}</flux:label>
                    <flux:error name="confirmAddress" />
                </flux:field>
                <flux:button type="submit" variant="primary">{{ __('Request withdrawal') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="space-y-3 text-sm lg:col-span-2">
            <flux:heading>{{ __('How withdrawals work') }}</flux:heading>
            <ol class="list-decimal space-y-2 pl-5 text-zinc-600 dark:text-zinc-300">
                <li>{{ __('The amount is held from your balance straight away.') }}</li>
                <li>{{ __('An admin reviews the request.') }}</li>
                <li>{{ __('If approved, it is marked as paid. In this demo nothing is sent on-chain.') }}</li>
                <li>{{ __('If rejected, the full amount returns to your balance.') }}</li>
            </ol>
        </flux:card>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <div class="px-4 pt-4"><flux:heading>{{ __('Your withdrawals') }}</flux:heading></div>
        <flux:table :paginate="$this->withdrawals" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Address') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->withdrawals as $withdrawal)
                    <flux:table.row :key="$withdrawal->id">
                        <flux:table.cell>{{ $withdrawal->created_at->format('j M Y, H:i') }}</flux:table.cell>
                        <flux:table.cell><x-btc :sats="$withdrawal->amount_sats" /></flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ Str::limit($withdrawal->btc_address, 14, '…') }}{{ substr($withdrawal->btc_address, -6) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$withdrawal->status->color()">{{ $withdrawal->status->label() }}</flux:badge>
                            @if ($withdrawal->admin_note)
                                <p class="mt-1 max-w-56 truncate text-xs text-zinc-500">{{ $withdrawal->admin_note }}</p>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">{{ __('No withdrawals yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
