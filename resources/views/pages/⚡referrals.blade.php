<?php

use App\Enums\LedgerType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Referrals')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function referrals(): LengthAwarePaginator
    {
        return Auth::user()->referrals()->latest()->paginate(15, ['id', 'name', 'created_at']);
    }

    #[Computed]
    public function bonusSats(): int
    {
        return (int) Auth::user()->ledgerEntries()->where('type', LedgerType::ReferralBonus)->sum('amount_micros');
    }
};
?>

<div wire:poll.20s data-live-page class="max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Referrals') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Invite friends with your link. When someone you invited has their first deposit approved, you get a bonus of :percent% of it.', ['percent' => rtrim(rtrim(number_format(Setting::integer('referral_bonus_bps') / 100, 2), '0'), '.')]) }}</flux:text>
    </div>

    <flux:card class="space-y-3">
        <flux:heading>{{ __('Your link') }}</flux:heading>
        <div class="flex flex-col gap-2 sm:flex-row">
            <flux:input readonly :value="auth()->user()->referralLink()" class="min-w-0 flex-1" />
            <flux:button data-copy="{{ auth()->user()->referralLink() }}" icon="clipboard-document"><span data-copy-label>{{ __('Copy link') }}</span></flux:button>
        </div>
        <flux:text class="text-sm">{{ __('Your code:') }} <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ auth()->user()->referral_code }}</span></flux:text>
    </flux:card>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-stat-card :label="__('People invited')" icon="users">{{ $this->referrals->total() }}</x-stat-card>
        <x-stat-card :label="__('Bonuses earned')" icon="gift"><x-usdt :micros="$this->bonusSats" /></x-stat-card>
    </div>

    <flux:card>
        <flux:heading>{{ __('People you invited') }}</flux:heading>
        <ul class="mt-3 divide-y divide-zinc-100 dark:divide-white/10">
            @forelse ($this->referrals as $referral)
                <li wire:key="ref-{{ $referral->id }}" class="flex justify-between py-2.5 text-sm">
                    <span>{{ Str::mask($referral->name, '*', 3) }}</span>
                    <span class="text-zinc-500">{{ __('Joined :date', ['date' => $referral->created_at->format('M j, Y')]) }}</span>
                </li>
            @empty
                <li class="py-2.5 text-sm text-zinc-500">{{ __('Nobody yet. Share your link to get started.') }}</li>
            @endforelse
        </ul>
        @if ($this->referrals->hasPages())
            <flux:pagination :paginator="$this->referrals" class="mt-3 border-t border-zinc-100 pt-3 dark:border-white/10" />
        @endif
    </flux:card>

    <flux:text class="text-xs">{{ __('Please share responsibly and do not spam.') }}</flux:text>
</div>
