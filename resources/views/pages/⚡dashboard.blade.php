<?php

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Enums\PlanCategory;
use App\Enums\RequestStatus;
use App\Models\Announcement;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Services\MarketPrices;
use App\Support\Btc;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * @return Collection<int, Announcement>
     */
    #[Computed]
    public function announcements(): Collection
    {
        return Announcement::query()->published()->limit(3)->get();
    }

    /**
     * @return Collection<int, Investment>
     */
    #[Computed]
    public function activeInvestments(): Collection
    {
        return Auth::user()->investments()->with('plan')->where('status', InvestmentStatus::Active)->latest('starts_at')->get();
    }

    /**
     * @return array{balance: int, invested: int, earned: int, pending_withdrawals: int}
     */
    #[Computed]
    public function totals(): array
    {
        $user = Auth::user();

        return [
            'balance' => $user->balance_sats,
            'invested' => (int) $this->activeInvestments->sum('amount_sats'),
            'earned' => (int) $user->ledgerEntries()->where('type', LedgerType::Earning)->sum('amount_sats')
                + (int) $this->activeInvestments->sum('earned_sats'),
            'pending_withdrawals' => (int) $user->withdrawals()->where('status', RequestStatus::Pending)->sum('amount_sats'),
        ];
    }

    /**
     * Balance after each of the last 30 changes, for the chart.
     *
     * @return list<array{label: string, value: int, text: string}>
     */
    #[Computed]
    public function balanceHistory(): array
    {
        return Auth::user()->ledgerEntries()->latest('id')->limit(30)->get()->reverse()->values()
            ->map(fn (LedgerEntry $entry): array => [
                'label' => $entry->created_at->format('M j'),
                'value' => $entry->balance_after_sats,
                'text' => Btc::format($entry->balance_after_sats).' BTC',
            ])->all();
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    #[Computed]
    public function recentEntries(): Collection
    {
        return Auth::user()->ledgerEntries()->latest('id')->limit(6)->get();
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->available()->get();
    }

    /**
     * The first steps a new user should take, and whether each is done.
     *
     * @return list<array{label: string, done: bool, url: string}>
     */
    #[Computed]
    public function gettingStarted(): array
    {
        $user = Auth::user();

        return [
            ['label' => __('Verify your identity'), 'done' => $user->isKycVerified(), 'url' => route('kyc')],
            ['label' => __('Make your first deposit'), 'done' => $user->deposits()->where('status', RequestStatus::Approved)->exists(), 'url' => route('deposit')],
            ['label' => __('Choose your first plan'), 'done' => $user->investments()->exists(), 'url' => route('invest')],
            ['label' => __('Turn on two-step login'), 'done' => $user->two_factor_confirmed_at !== null, 'url' => route('security.edit')],
        ];
    }

    #[Computed]
    public function btcPriceCents(): ?int
    {
        return app(MarketPrices::class)->cached()['btc_cents'];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Welcome back, :name', ['name' => Str::before(auth()->user()->name, ' ')]) }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your portfolio at a glance.') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('deposit')" icon="arrow-down-tray" wire:navigate>{{ __('Deposit') }}</flux:button>
            <flux:button :href="route('invest')" variant="primary" icon="sparkles" wire:navigate>{{ __('Invest') }}</flux:button>
        </div>
    </div>

    @foreach ($this->announcements as $announcement)
        <flux:callout :color="$announcement->calloutColor()" icon="megaphone" wire:key="announcement-{{ $announcement->id }}">
            <flux:callout.heading>{{ $announcement->title }}</flux:callout.heading>
            <flux:callout.text>{{ $announcement->body }}</flux:callout.text>
        </flux:callout>
    @endforeach

    @php
        $steps = collect($this->gettingStarted);
    @endphp
    @if ($steps->contains('done', false))
        <flux:card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading>{{ __('Getting started') }}</flux:heading>
                <flux:text class="text-sm">{{ __(':done of :total done', ['done' => $steps->where('done', true)->count(), 'total' => $steps->count()]) }}</flux:text>
            </div>
            <flux:progress class="mt-3" :value="intdiv($steps->where('done', true)->count() * 100, $steps->count())" />
            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($steps as $step)
                    <a href="{{ $step['url'] }}" wire:navigate @class(['flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition', 'border-green-200 bg-green-50 text-green-800 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-300' => $step['done'], 'border-zinc-200 hover:border-brand-400 dark:border-white/10' => ! $step['done']])>
                        <flux:icon :name="$step['done'] ? 'check-circle' : 'arrow-right-circle'" variant="mini" @class(['shrink-0', 'text-brand-600' => ! $step['done']]) />
                        {{ $step['label'] }}
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    @unless (auth()->user()->isKycVerified())
        <flux:callout color="sky" icon="identification">
            <flux:callout.heading>{{ auth()->user()->kycStatus()?->value === 'pending' ? __('Your identity check is under review') : __('Verify your identity') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Identity verification is needed before you can withdraw.') }}</flux:callout.text>
            @if (auth()->user()->kycStatus()?->value !== 'pending')
                <x-slot name="actions">
                    <flux:button size="sm" :href="route('kyc')" wire:navigate>{{ __('Start verification') }}</flux:button>
                </x-slot>
            @endif
        </flux:callout>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Available balance')" icon="wallet" :hint="$this->btcPriceCents ? '≈ '.Btc::usd(Btc::usdCents($this->totals['balance'], $this->btcPriceCents)) : null">
            <x-btc :sats="$this->totals['balance']" />
        </x-stat-card>
        <x-stat-card :label="__('In active plans')" icon="chart-pie" :hint="trans_choice(':count active plan|:count active plans', $this->activeInvestments->count())">
            <x-btc :sats="$this->totals['invested']" />
        </x-stat-card>
        <x-stat-card :label="__('Earnings')" icon="arrow-trending-up" :hint="__('Paid and accruing')">
            <x-btc :sats="$this->totals['earned']" />
        </x-stat-card>
        <x-stat-card :label="__('Pending withdrawals')" icon="arrow-up-tray" :hint="__('Waiting for admin review')">
            <x-btc :sats="$this->totals['pending_withdrawals']" />
        </x-stat-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card class="xl:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Balance history') }}</flux:heading>
                <flux:text class="text-xs">{{ __('Last 30 changes') }}</flux:text>
            </div>
            <x-chart.line class="mt-6" :points="$this->balanceHistory" :empty="__('Your balance history will appear after your first deposit.')" />
        </flux:card>

        <flux:card>
            <flux:heading>{{ __('Allocation') }}</flux:heading>
            @php
                $segments = [['label' => __('Available'), 'value' => $this->totals['balance'], 'color' => '#4f6cb8', 'text' => Btc::format($this->totals['balance']).' BTC']];
                foreach (PlanCategory::cases() as $category) {
                    $inPlans = (int) $this->activeInvestments->filter(fn ($investment) => $investment->plan->category === $category)->sum('amount_sats');
                    if ($inPlans > 0 || in_array($category, [PlanCategory::Bitcoin, PlanCategory::Gold], true)) {
                        $segments[] = ['label' => __($category->label()), 'value' => $inPlans, 'color' => $category->color(), 'text' => Btc::format($inPlans).' BTC'];
                    }
                }
            @endphp
            <x-chart.donut class="mt-6" :segments="$segments">
                <x-slot name="center">
                    <span class="text-[11px] text-zinc-500">{{ __('Total') }}</span>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ Btc::format($this->totals['balance'] + $this->totals['invested']) }}</span>
                </x-slot>
            </x-chart.donut>
        </flux:card>
    </div>

    @if ($this->plans->isNotEmpty())
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading class="flex items-center gap-2"><flux:icon.calculator variant="mini" class="text-brand-600" /> {{ __('BTC plan calculator') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Your balance: :amount BTC', ['amount' => Btc::format($this->totals['balance'])]) }}</flux:text>
            </div>
            <x-returns-calculator class="mt-3" :plans="$this->plans" :btc-price-cents="$this->btcPriceCents" />
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Active plans') }}</flux:heading>
                <flux:link :href="route('investments')" wire:navigate class="text-sm">{{ __('View all') }}</flux:link>
            </div>
            <div class="mt-4 space-y-4">
                @forelse ($this->activeInvestments->take(4) as $investment)
                    <div wire:key="inv-{{ $investment->id }}" class="rounded-lg border border-zinc-100 p-3 dark:border-white/10">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $investment->plan->name }}</span>
                            <x-btc :sats="$investment->amount_sats" class="font-medium" />
                        </div>
                        <flux:progress class="mt-2" :value="$investment->progressPercent()" />
                        <div class="mt-1.5 flex justify-between text-xs text-zinc-500">
                            <span>{{ __('Ends :date', ['date' => $investment->ends_at->format('M j, Y')]) }}</span>
                            <span>{{ __('Earned so far:') }} <x-btc :sats="$investment->earned_sats" /></span>
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('No active plans yet.') }} <flux:link :href="route('invest')" wire:navigate>{{ __('Compare plans') }}</flux:link></flux:text>
                @endforelse
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Recent activity') }}</flux:heading>
                <flux:link :href="route('transactions')" wire:navigate class="text-sm">{{ __('All transactions') }}</flux:link>
            </div>
            <ul class="mt-4 divide-y divide-zinc-100 dark:divide-white/10">
                @forelse ($this->recentEntries as $entry)
                    <li wire:key="entry-{{ $entry->id }}" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $entry->type->label() }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $entry->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                        <x-btc :sats="$entry->amount_sats" signed @class(['font-medium', 'text-green-600 dark:text-green-400' => $entry->amount_sats > 0, 'text-zinc-700 dark:text-zinc-300' => $entry->amount_sats < 0]) />
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-zinc-500">{{ __('No activity yet.') }}</li>
                @endforelse
            </ul>
        </flux:card>
    </div>

    <flux:card class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading>{{ __('Your referral link') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Share it with friends and earn a bonus on their first deposit.') }}</flux:text>
        </div>
        <div class="flex min-w-0 items-center gap-2">
            <flux:input readonly :value="auth()->user()->referralLink()" class="min-w-0 sm:w-80" />
            <flux:button data-copy="{{ auth()->user()->referralLink() }}" icon="clipboard-document"><span data-copy-label>{{ __('Copy') }}</span></flux:button>
        </div>
    </flux:card>
</div>
