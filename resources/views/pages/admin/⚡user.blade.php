<?php

use App\Exceptions\WalletException;
use App\Models\User;
use App\Services\WalletService;
use App\Support\Btc;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('User')] class extends Component {
    public User $user;

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public function adjust(WalletService $wallet): void
    {
        $this->validate([
            'adjustAmount' => ['required', 'regex:/^-?\d{1,8}(\.\d{1,8})?$/'],
            'adjustReason' => ['required', 'string', 'min:5', 'max:200'],
        ], [
            'adjustAmount.regex' => __('Enter a BTC amount, e.g. 0.01 to add or -0.01 to take away.'),
        ]);

        $negative = str_starts_with($this->adjustAmount, '-');
        $sats = (int) Btc::toSats(ltrim($this->adjustAmount, '-'));

        try {
            $wallet->adjust($this->user, $negative ? -$sats : $sats, 'Admin adjustment: '.$this->adjustReason, Auth::user());
        } catch (WalletException $exception) {
            $this->addError('adjustAmount', $exception->getMessage());

            return;
        }

        $this->user->refresh();
        $this->reset('adjustAmount', 'adjustReason');
        Flux::toast(variant: 'success', text: __('Balance adjusted and logged.'));
    }

    public function toggleSuspension(): void
    {
        abort_if($this->user->is(Auth::user()), 422, 'You cannot suspend yourself.');

        $this->user->forceFill(['is_suspended' => ! $this->user->is_suspended])->save();

        Flux::toast(text: $this->user->is_suspended ? __('User suspended.') : __('User restored.'));
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('admin.users')" wire:navigate class="text-sm">← {{ __('All users') }}</flux:link>
            <flux:heading size="xl" class="mt-2">{{ $user->name }}</flux:heading>
            <flux:text>{{ $user->email }} · {{ __('Joined :date', ['date' => $user->created_at->format('M j, Y')]) }} · {{ __('Code') }} <span class="font-mono">{{ $user->referral_code }}</span></flux:text>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <flux:badge size="sm" :color="$user->hasVerifiedEmail() ? 'green' : 'zinc'">{{ $user->hasVerifiedEmail() ? __('Email verified') : __('Email not verified') }}</flux:badge>
                <flux:badge size="sm" :color="$user->two_factor_confirmed_at ? 'green' : 'zinc'">{{ $user->two_factor_confirmed_at ? __('Two-step on') : __('Two-step off') }}</flux:badge>
                @if ($user->kycStatus()) <flux:badge size="sm" :color="$user->kycStatus()->color()">{{ __('Identity: :status', ['status' => $user->kycStatus()->label()]) }}</flux:badge> @endif
                @if ($user->is_suspended) <flux:badge size="sm" color="red">{{ __('Suspended') }}</flux:badge> @endif
            </div>
        </div>
        @unless ($user->is(auth()->user()))
            <flux:button wire:click="toggleSuspension" wire:confirm="{{ $user->is_suspended ? __('Restore this user?') : __('Suspend this user? They will be logged out.') }}" :variant="$user->is_suspended ? 'filled' : 'danger'">
                {{ $user->is_suspended ? __('Restore user') : __('Suspend user') }}
            </flux:button>
        @endunless
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Balance')" icon="wallet"><x-btc :sats="$user->balance_sats" /></x-stat-card>
        <x-stat-card :label="__('In active plans')" icon="chart-pie"><x-btc :sats="$user->investedSats()" /></x-stat-card>
        <x-stat-card :label="__('Invited users')" icon="users">{{ $user->referrals()->count() }}</x-stat-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card>
            <form wire:submit="adjust" class="space-y-4">
                <flux:heading>{{ __('Adjust balance') }}</flux:heading>
                <flux:text class="text-sm">{{ __('For corrections only. Every adjustment is written to the audit log with your name.') }}</flux:text>
                <flux:input wire:model="adjustAmount" :label="__('Amount (BTC)')" placeholder="0.01 or -0.01" inputmode="decimal" />
                <flux:input wire:model="adjustReason" :label="__('Reason')" />
                <flux:button type="submit" variant="primary">{{ __('Apply adjustment') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="!p-0 overflow-hidden xl:col-span-2">
            <div class="px-4 pt-4"><flux:heading>{{ __('Recent balance changes') }}</flux:heading></div>
            <flux:table class="[&_td]:px-4! [&_th]:px-4!">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Details') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Balance after') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($user->ledgerEntries()->with('actor:id,name')->latest('id')->limit(15)->get() as $entry)
                        <flux:table.row :key="$entry->id">
                            <flux:table.cell class="whitespace-nowrap">{{ $entry->created_at->format('M j, g:i A') }}</flux:table.cell>
                            <flux:table.cell>
                                <p>{{ $entry->description }}</p>
                                @if ($entry->actor) <p class="text-xs text-zinc-500">{{ __('by :name', ['name' => $entry->actor->name]) }}</p> @endif
                            </flux:table.cell>
                            <flux:table.cell align="end"><x-btc :sats="$entry->amount_sats" signed /></flux:table.cell>
                            <flux:table.cell align="end"><x-btc :sats="$entry->balance_after_sats" /></flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="4" class="text-center">{{ __('No changes yet.') }}</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</div>
