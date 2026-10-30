<?php

use App\Models\Setting;
use App\Rules\BitcoinAddress;
use App\Support\Btc;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Site settings')] class extends Component {
    public string $siteName = '';

    public string $tagline = '';

    public string $supportEmail = '';

    public string $seoDescription = '';

    public string $companyDetails = '';

    public string $depositAddress = '';

    public string $explorerTxUrl = '';

    public string $minDeposit = '';

    public string $minWithdrawal = '';

    public bool $withdrawalRequiresKyc = true;

    public string $referralBonusPercent = '';

    public function mount(): void
    {
        $this->siteName = Setting::get('site_name');
        $this->tagline = Setting::get('tagline');
        $this->supportEmail = Setting::get('support_email');
        $this->seoDescription = Setting::get('seo_description');
        $this->companyDetails = Setting::get('company_details');
        $this->depositAddress = Setting::get('deposit_address');
        $this->explorerTxUrl = Setting::get('explorer_tx_url');
        $this->minDeposit = Btc::format(Setting::integer('min_deposit_sats'));
        $this->minWithdrawal = Btc::format(Setting::integer('min_withdrawal_sats'));
        $this->withdrawalRequiresKyc = Setting::enabled('withdrawal_requires_kyc');
        $this->referralBonusPercent = rtrim(rtrim(number_format(Setting::integer('referral_bonus_bps') / 100, 2, '.', ''), '0'), '.');
    }

    public function save(): void
    {
        $this->depositAddress = trim($this->depositAddress);

        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:60'],
            'tagline' => ['required', 'string', 'max:160'],
            'supportEmail' => ['required', 'email', 'max:120'],
            'seoDescription' => ['required', 'string', 'max:300'],
            'companyDetails' => ['nullable', 'string', 'max:500'],
            'depositAddress' => ['nullable', 'string', 'max:100', new BitcoinAddress],
            'explorerTxUrl' => ['required', 'url:https', 'max:200'],
            'minDeposit' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'minWithdrawal' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'withdrawalRequiresKyc' => ['boolean'],
            'referralBonusPercent' => ['required', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
        ]);

        [$whole, $fraction] = array_pad(explode('.', $validated['referralBonusPercent']), 2, '');

        Setting::put([
            'site_name' => $validated['siteName'],
            'tagline' => $validated['tagline'],
            'support_email' => $validated['supportEmail'],
            'seo_description' => $validated['seoDescription'],
            'company_details' => $validated['companyDetails'],
            'deposit_address' => $validated['depositAddress'],
            'explorer_tx_url' => $validated['explorerTxUrl'],
            'min_deposit_sats' => Btc::toSats($validated['minDeposit']),
            'min_withdrawal_sats' => Btc::toSats($validated['minWithdrawal']),
            'withdrawal_requires_kyc' => $validated['withdrawalRequiresKyc'],
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
            <flux:textarea wire:model="companyDetails" :label="__('Company registration and licence details (shown in the footer)')" rows="2" :description="__('For example the RC number and SEC registration number.')" />
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading>{{ __('Wallet') }}</flux:heading>
            <flux:input wire:model="depositAddress" :label="__('Company BTC deposit address')" class="font-mono"
                :description="__('Customers send BTC here. Leave empty to close deposits. Double-check it: coins sent to a wrong address are lost.')" />
            <flux:input wire:model="explorerTxUrl" :label="__('Blockchain explorer link for transactions')" :description="__('Used for the Check on blockchain links, e.g. https://mempool.space/tx/')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="minDeposit" :label="__('Minimum deposit (BTC)')" inputmode="decimal" />
                <flux:input wire:model="minWithdrawal" :label="__('Minimum withdrawal (BTC)')" inputmode="decimal" />
                <flux:input wire:model="referralBonusPercent" :label="__('Referral bonus (%)')" inputmode="decimal" />
            </div>
            <flux:switch wire:model="withdrawalRequiresKyc" :label="__('Require verified identity before withdrawals')" />
        </flux:card>

        <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
    </form>
</div>
