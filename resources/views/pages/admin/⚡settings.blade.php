<?php

use App\Models\Setting;
use App\Support\Usdt;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Site settings')] class extends Component {
    public string $siteName = '';

    public string $tagline = '';

    public string $supportEmail = '';

    public string $seoDescription = '';

    public string $companyDetails = '';

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
        $this->minDeposit = str_replace(',', '', Usdt::format(Setting::integer('min_deposit_micros')));
        $this->minWithdrawal = str_replace(',', '', Usdt::format(Setting::integer('min_withdrawal_micros')));
        $this->withdrawalRequiresKyc = Setting::enabled('withdrawal_requires_kyc');
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
            'min_deposit_micros' => Usdt::toMicros($validated['minDeposit']),
            'min_withdrawal_micros' => Usdt::toMicros($validated['minWithdrawal']),
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
                <flux:input wire:model="referralBonusPercent" :label="__('Referral bonus (%)')" inputmode="decimal" />
            </div>
            <flux:switch wire:model="withdrawalRequiresKyc" :label="__('Require verified identity before withdrawals')" />
        </flux:card>

        <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
    </form>
</div>
