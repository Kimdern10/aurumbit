<?php

use App\Models\Setting;
use App\Support\Usdt;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Site settings')] class extends Component {
    /**
     * Only owners may open this page or use its actions.
     */
    public function boot(): void
    {
        Gate::authorize('manage-money');
    }

    public string $siteName = '';

    public string $tagline = '';

    public string $supportEmail = '';

    public string $seoDescription = '';

    public string $companyDetails = '';

    public string $minDeposit = '';

    public string $minWithdrawal = '';

    public string $dailyWithdrawalLimit = '';

    public bool $withdrawalRequiresKyc = true;

    public bool $autoCreditDeposits = false;

    public bool $autoCreditCrypto = false;

    public string $autoCreditMax = '';

    public string $referralBonusPercent = '';

    public function mount(): void
    {
        $this->siteName = Setting::get('site_name');
        $this->tagline = Setting::get('tagline');
        $this->supportEmail = Setting::get('support_email');
        $this->seoDescription = Setting::get('seo_description');
        $this->companyDetails = Setting::get('company_details');
        $this->minDeposit = str_replace(',', '', Usdt::format(Setting::integer('min_deposit_micros')));
        $this->minWithdrawal = str_replace(',', '', Usdt::format(Setting::integer('min_withdrawal_micros')));
        $this->dailyWithdrawalLimit = Setting::integer('daily_withdrawal_limit_micros') > 0 ? str_replace(',', '', Usdt::format(Setting::integer('daily_withdrawal_limit_micros'))) : '';
        $this->withdrawalRequiresKyc = Setting::enabled('withdrawal_requires_kyc');
        $this->autoCreditDeposits = Setting::enabled('auto_credit_deposits');
        $this->autoCreditCrypto = Setting::enabled('auto_credit_crypto');
        $this->autoCreditMax = Setting::integer('auto_credit_max_micros') > 0 ? str_replace(',', '', Usdt::format(Setting::integer('auto_credit_max_micros'))) : '';
        $this->referralBonusPercent = rtrim(rtrim(number_format(Setting::integer('referral_bonus_bps') / 100, 2, '.', ''), '0'), '.');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:60'],
            'tagline' => ['required', 'string', 'max:160'],
            'supportEmail' => ['required', 'email', 'max:120'],
            'seoDescription' => ['required', 'string', 'max:300'],
            'companyDetails' => ['nullable', 'string', 'max:500'],
            'minDeposit' => ['required', 'regex:'.Usdt::INPUT_PATTERN],
            'minWithdrawal' => ['required', 'regex:'.Usdt::INPUT_PATTERN],
            'dailyWithdrawalLimit' => ['nullable', 'regex:'.Usdt::INPUT_PATTERN],
            'withdrawalRequiresKyc' => ['boolean'],
            'autoCreditDeposits' => ['boolean'],
            'autoCreditCrypto' => ['boolean'],
            'autoCreditMax' => ['nullable', 'regex:'.Usdt::INPUT_PATTERN],
            'referralBonusPercent' => ['required', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
        ]);

        [$whole, $fraction] = array_pad(explode('.', $validated['referralBonusPercent']), 2, '');

        Setting::put([
            'site_name' => $validated['siteName'],
            'tagline' => $validated['tagline'],
            'support_email' => $validated['supportEmail'],
            'seo_description' => $validated['seoDescription'],
            'company_details' => $validated['companyDetails'],
            'min_deposit_micros' => Usdt::toMicros($validated['minDeposit']),
            'min_withdrawal_micros' => Usdt::toMicros($validated['minWithdrawal']),
            'daily_withdrawal_limit_micros' => filled($validated['dailyWithdrawalLimit']) ? Usdt::toMicros($validated['dailyWithdrawalLimit']) : 0,
            'withdrawal_requires_kyc' => $validated['withdrawalRequiresKyc'],
            'auto_credit_deposits' => $validated['autoCreditDeposits'],
            'auto_credit_crypto' => $validated['autoCreditCrypto'],
            'auto_credit_max_micros' => filled($validated['autoCreditMax']) ? Usdt::toMicros($validated['autoCreditMax']) : 0,
            'referral_bonus_bps' => (int) $whole * 100 + (int) str_pad($fraction, 2, '0'),
        ]);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }
};
?>

<div class="max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Site settings') }}</flux:heading>
        <flux:text class="mt-1">{{ __('The site never stores wallet private keys. Deposits are checked on the blockchain by an admin, and withdrawals are paid from the company wallet.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:heading>{{ __('Brand and search engines') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="siteName" :label="__('Site name')" />
                <flux:input wire:model="supportEmail" type="email" :label="__('Support email')" />
            </div>
            <flux:input wire:model="tagline" :label="__('Tagline')" />
            <flux:textarea wire:model="seoDescription" :label="__('Description for Google')" rows="2" />
            <flux:textarea wire:model="companyDetails" :label="__('Company registration details (shown in the footer)')" rows="2" :description="__('For example your legal company name, state of incorporation and any SEC, FINRA or FinCEN registration numbers.')" />
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading>{{ __('Balances (USDT)') }}</flux:heading>
                <flux:button size="sm" icon="wallet" :href="route('admin.wallets')" wire:navigate>{{ __('Manage deposit wallets') }}</flux:button>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="minDeposit" :label="__('Minimum deposit (USDT)')" inputmode="decimal" :description="__('Checked at today\'s price for coins we have a price for.')" />
                <flux:input wire:model="minWithdrawal" :label="__('Minimum withdrawal (USDT)')" inputmode="decimal" />
                <flux:input wire:model="dailyWithdrawalLimit" :label="__('Daily withdrawal limit per user (USDT)')" inputmode="decimal" :placeholder="__('No limit')" :description="__('Leave empty for no limit.')" />
                <flux:input wire:model="referralBonusPercent" :label="__('Referral bonus (%)')" inputmode="decimal" />
            </div>
            <flux:switch wire:model="withdrawalRequiresKyc" :label="__('Require verified identity before withdrawals')" />
        </flux:card>

        <flux:card class="space-y-4" data-auto-credit-settings>
            <div>
                <flux:heading>{{ __('Auto-credit deposits') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Every two minutes, pending deposits are checked against your Bybit account. A deposit is credited without an admin only when Bybit shows the same transaction ID, coin and amount, finished, at the address the user picked. Anything else waits for review as usual.') }}</flux:text>
            </div>
            @unless (app(\App\Services\BybitAccount::class)->isConfigured())
                <flux:callout color="amber" icon="exclamation-triangle">
                    <flux:callout.text>{{ __('Bybit is not connected (BYBIT_API_KEY in .env), so nothing will be credited automatically.') }}</flux:callout.text>
                </flux:callout>
            @endunless
            <flux:switch wire:model="autoCreditDeposits" :label="__('Credit confirmed deposits automatically')" />
            <flux:switch wire:model="autoCreditCrypto" :label="__('Also BTC, ETH and SOL at the live price')" :description="__('Off: only USDT and USDC are credited automatically (1 coin = 1 USDT). Other coins wait for an admin.')" />
            <flux:input wire:model="autoCreditMax" :label="__('Largest deposit to credit automatically (USDT)')" inputmode="decimal" :placeholder="__('No limit')" :description="__('Bigger deposits wait for an admin. Leave empty for no limit.')" class="sm:max-w-xs" />
        </flux:card>

        <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
    </form>
</div>
