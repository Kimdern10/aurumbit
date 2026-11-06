<?php

use App\Enums\WithdrawalNetwork;
use App\Exceptions\WalletException;
use App\Models\Setting;
use App\Models\Withdrawal;
use App\Rules\UsdtAddress;
use App\Services\WalletService;
use App\Support\Usdt;
use App\Support\WithdrawalCode;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Withdraw')] class extends Component {
    use WithPagination;

    public string $amount = '';

    public string $network = 'trc20';

    public string $address = '';

    public bool $confirmAddress = false;

    /**
     * The authenticator code, or the code emailed to the user, that confirms the withdrawal.
     */
    public string $securityCode = '';

    public bool $codeSent = false;

    /**
     * Email a withdrawal code, at most once a minute (users without two-step login).
     */
    public function sendCode(): void
    {
        $key = 'withdrawal-code-send:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $this->addError('securityCode', __('Please wait :seconds seconds before asking for another code.', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        RateLimiter::hit($key, 60);
        WithdrawalCode::send(Auth::user());
        $this->codeSent = true;
        $this->resetErrorBag('securityCode');
    }

    /**
     * @return LengthAwarePaginator<int, Withdrawal>
     */
    #[Computed]
    public function withdrawals(): LengthAwarePaginator
    {
        return Auth::user()->withdrawals()->latest('id')->paginate(8);
    }

    #[Computed]
    public function selectedNetwork(): ?WithdrawalNetwork
    {
        $network = WithdrawalNetwork::tryFrom($this->network);

        return in_array($network, WithdrawalNetwork::selectable(), true) ? $network : null;
    }

    /**
     * What the typed amount pays in the chosen coin at today's price, e.g. "0.01300000 BTC".
     */
    #[Computed]
    public function quote(): ?string
    {
        $micros = Usdt::toMicros($this->amount);

        if (! $micros || $this->selectedNetwork === null || $this->selectedNetwork->isUsdt()) {
            return null;
        }

        try {
            return app(WalletService::class)->quoteWithdrawal($micros, $this->selectedNetwork)['coin_amount'].' '.$this->selectedNetwork->coin();
        } catch (WalletException) {
            return null;
        }
    }

    public function save(WalletService $wallet): void
    {
        $this->address = trim($this->address);

        $this->validate([
            'amount' => ['required', 'regex:'.Usdt::INPUT_PATTERN],
            'network' => ['required', Rule::in(array_map(fn (WithdrawalNetwork $network): string => $network->value, WithdrawalNetwork::selectable()))],
            'address' => ['required', 'string', 'max:100', new UsdtAddress($this->selectedNetwork)],
            'confirmAddress' => ['accepted'],
            'securityCode' => ['required', 'regex:/^\s*\d{3}\s?\d{3}\s*$/'],
        ], [
            'amount.regex' => __('Enter an amount in USDT, with up to 2 decimal places.'),
            'confirmAddress.accepted' => __('Please confirm the address is yours and correct.'),
            'securityCode.required' => WithdrawalCode::usesAuthenticator(Auth::user()) ? __('Enter the 6-digit code from your authenticator app.') : __('Enter the 6-digit code we emailed you.'),
            'securityCode.regex' => __('The code has 6 digits.'),
        ]);

        $key = 'withdraw:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('amount', __('Too many requests. Please try again in a few minutes.'));

            return;
        }

        RateLimiter::hit($key, 600);

        if (! WithdrawalCode::verify(Auth::user(), $this->securityCode)) {
            $this->reset('securityCode');
            $this->addError('securityCode', WithdrawalCode::usesAuthenticator(Auth::user())
                ? __('That authenticator code is not right. Codes change every 30 seconds.')
                : __('That code is not right or has expired. Send a new code.'));

            return;
        }

        try {
            $wallet->requestWithdrawal(Auth::user(), (int) Usdt::toMicros($this->amount), $this->selectedNetwork, $this->address);
        } catch (WalletException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->reset('amount', 'address', 'confirmAddress', 'securityCode', 'codeSent');
        $this->resetPage();
        Flux::toast(variant: 'success', text: __('Withdrawal requested. The amount is on hold until an admin reviews it.'));
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Withdraw') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Available balance:') }} <x-usdt :micros="auth()->user()->balance_micros" class="font-semibold text-zinc-900 dark:text-white" /></flux:text>
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
                <flux:input wire:model.live.debounce.400ms="amount" :label="__('Amount from your balance (USDT)')" inputmode="decimal" autocomplete="off" placeholder="100.00"
                    :description="__('Minimum :min USDT', ['min' => Usdt::format(Setting::integer('min_withdrawal_micros'))])" />
                <flux:radio.group wire:model.live="network" :label="__('Receive it in')" variant="cards" class="grid! grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach (WithdrawalNetwork::selectable() as $option)
                        <flux:radio :value="$option->value" :label="$option->label()" />
                    @endforeach
                </flux:radio.group>
                @if ($this->quote)
                    <flux:callout color="sky" icon="arrows-right-left" data-withdraw-quote>
                        <flux:callout.text>{{ __('You receive about :quote at today\'s price. The amount is fixed when you submit.', ['quote' => $this->quote]) }}</flux:callout.text>
                    </flux:callout>
                @endif
                <flux:input wire:model.blur="address" :label="__('Your :coin address', ['coin' => $this->selectedNetwork?->shortLabel()])" autocomplete="off" spellcheck="false" class="font-mono"
                    :placeholder="$this->selectedNetwork?->addressPlaceholder()" :description="$this->selectedNetwork?->addressHint()" />
                <flux:field variant="inline">
                    <flux:checkbox wire:model="confirmAddress" />
                    <flux:label class="text-sm leading-snug">{{ __('This address belongs to me, it is on the network I chose, and I have checked every character. Crypto sent to a wrong address or network cannot be recovered.') }}</flux:label>
                    <flux:error name="confirmAddress" />
                </flux:field>

                <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-white/10" data-withdrawal-security>
                    <p class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white"><flux:icon.shield-check variant="mini" class="text-brand-600" /> {{ __('Confirm it\'s you') }}</p>
                    @if (WithdrawalCode::usesAuthenticator(auth()->user()))
                        <flux:input wire:model="securityCode" :label="__('Code from your authenticator app')" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000" class="font-mono" />
                    @else
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:input wire:model="securityCode" :label="__('6-digit code sent to :email', ['email' => auth()->user()->email])" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000000" class="min-w-0 flex-1 font-mono" />
                            <flux:button type="button" wire:click="sendCode" icon="envelope">{{ $codeSent ? __('Send again') : __('Email me a code') }}</flux:button>
                        </div>
                        @if ($codeSent)
                            <flux:text class="text-xs text-green-700 dark:text-green-400">{{ __('Code sent. It works for :minutes minutes.', ['minutes' => WithdrawalCode::MINUTES_VALID]) }}</flux:text>
                        @endif
                        <flux:text class="text-xs">{{ __('Tip: turn on two-step login in Settings to use your authenticator app instead.') }}</flux:text>
                    @endif
                </div>

                <flux:button type="submit" variant="primary">{{ __('Request withdrawal') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="space-y-3 text-sm lg:col-span-2">
            <flux:heading>{{ __('How withdrawals work') }}</flux:heading>
            <ol class="list-decimal space-y-2 pl-5 text-zinc-600 dark:text-zinc-300">
                <li>{{ __('The USDT amount is held from your balance straight away. For BTC, ETH or SOL, the coin amount is fixed at the price when you ask.') }}</li>
                <li>{{ __('An admin reviews the request.') }}</li>
                <li>{{ __('If approved, we send the coin you chose to your address and show you the transaction ID.') }}</li>
                <li>{{ __('If rejected, the full amount returns to your balance.') }}</li>
            </ol>
        </flux:card>
    </div>

    <x-coin-converter />

    @island
    <div wire:poll.20s data-live-history>
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
                        <flux:table.cell>{{ $withdrawal->created_at->format('M j, Y g:i A') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            <span class="font-medium tabular-nums">{{ $withdrawal->payoutLabel() }}</span> <a href="{{ $withdrawal->receiptUrl() }}" class="font-mono text-[11px] text-brand-600 underline">{{ $withdrawal->code() }}</a>
                            @if ($withdrawal->coin !== 'USDT') <p class="text-xs text-zinc-500"><x-usdt :micros="$withdrawal->amount_micros" /></p> @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            <flux:badge size="sm" class="me-1">{{ $withdrawal->network->shortLabel() }}</flux:badge>
                            {{ Str::limit($withdrawal->address, 10, '…') }}{{ substr($withdrawal->address, -6) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$withdrawal->status->color()">{{ $withdrawal->status->label() }}</flux:badge>
                            @if ($url = $withdrawal->payoutUrl())
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="mt-1 block text-xs underline">{{ __('View payment') }}</a>
                            @endif
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
    @endisland
</div>
