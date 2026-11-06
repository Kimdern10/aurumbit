<?php

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AdminCounts;
use App\Support\Usdt;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin overview')] class extends Component {
    use WithPagination;

    /**
     * @return array{users: int, balances: int, invested: int, active: int}
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'users' => User::query()->where('is_admin', false)->count(),
            'balances' => (int) User::query()->sum('balance_micros'),
            'invested' => (int) Investment::query()->where('status', InvestmentStatus::Active)->sum('amount_micros'),
            'active' => Investment::query()->where('status', InvestmentStatus::Active)->count(),
        ];
    }

    /**
     * Approved deposits per day over the last 14 days.
     *
     * @return list<array{label: string, value: int, text: string}>
     */
    #[Computed]
    public function depositsByDay(): array
    {
        $since = now()->subDays(13)->startOfDay();

        $sums = LedgerEntry::query()
            ->where('type', LedgerType::Deposit)
            ->where('created_at', '>=', $since)
            ->get(['amount_micros', 'created_at'])
            ->groupBy(fn (LedgerEntry $entry): string => $entry->created_at->format('Y-m-d'))
            ->map(fn ($entries): int => (int) $entries->sum('amount_micros'));

        return collect(range(0, 13))->map(function (int $offset) use ($since, $sums): array {
            $day = $since->addDays($offset);
            $value = $sums[$day->format('Y-m-d')] ?? 0;

            return ['label' => $day->format('M j'), 'value' => $value, 'text' => Usdt::format($value).' USDT'];
        })->all();
    }

    /**
     * The latest balance changes, 8 per page.
     *
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    #[Computed]
    public function recent(): LengthAwarePaginator
    {
        return LedgerEntry::query()->with('user:id,name')->latest('id')->paginate(8, pageName: 'changes');
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Admin overview') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Customer balances, plans and requests waiting for you.') }}</flux:text>
    </div>

    @php($pending = AdminCounts::pending())
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['label' => __('Deposits to review'), 'count' => $pending['deposits'], 'route' => 'admin.deposits', 'icon' => 'arrow-down-tray'],
            ['label' => __('Withdrawals to review'), 'count' => $pending['withdrawals'], 'route' => 'admin.withdrawals', 'icon' => 'arrow-up-tray'],
            ['label' => __('Identity checks to review'), 'count' => $pending['kyc'], 'route' => 'admin.kyc', 'icon' => 'identification'],
        ] as $queue)
            <a href="{{ route($queue['route']) }}" wire:navigate.hover class="group flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-brand-400 dark:border-white/10 dark:bg-ink-900">
                <div>
                    <flux:text class="text-sm">{{ $queue['label'] }}</flux:text>
                    <p class="mt-1 font-display text-2xl font-semibold text-zinc-900 dark:text-white">{{ $queue['count'] }}</p>
                </div>
                <flux:icon :name="$queue['icon']" @class(['size-8', 'text-brand-500' => $queue['count'] > 0, 'text-zinc-300 dark:text-zinc-600' => $queue['count'] === 0]) />
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Users')" icon="users">{{ number_format($this->totals['users']) }}</x-stat-card>
        @php($money = \App\Support\MoneySummary::totals())
        <x-stat-card :label="__('Customer balances')" icon="wallet"
            :hint="$money['adjustments'] !== 0 ? __('Includes :amount USDT from admin adjustments', ['amount' => ($money['adjustments'] > 0 ? '+' : '').\App\Support\Usdt::format($money['adjustments'])]) : __('All from deposits and earnings')">
            <x-usdt :micros="$this->totals['balances']" />
        </x-stat-card>
        <x-stat-card :label="__('In active plans')" icon="chart-pie"><x-usdt :micros="$this->totals['invested']" /></x-stat-card>
        <x-stat-card :label="__('Active investments')" icon="sparkles">{{ number_format($this->totals['active']) }}</x-stat-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card class="space-y-4 xl:col-span-2" data-money-summary>
            <div>
                <flux:heading>{{ __('Real money vs. money added on the platform') }}</flux:heading>
                <flux:text class="text-sm">{{ __('Real money is only what customers actually deposited. Admin adjustments, plan earnings and referral bonuses were added by the platform and are never counted as real money.') }}</flux:text>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-500/20 dark:bg-green-500/10">
                    <p class="text-xs font-semibold tracking-wide text-green-700 uppercase dark:text-green-400">{{ __('Real money deposited') }}</p>
                    <p class="mt-1 font-display text-2xl font-semibold [overflow-wrap:anywhere] text-green-800 dark:text-green-300"><x-usdt :micros="$money['real_deposits']" data-real-deposits /></p>
                    <dl class="mt-3 space-y-1.5 border-t border-green-200 pt-3 text-sm dark:border-green-500/20">
                        <div class="flex justify-between gap-3"><dt>{{ __('Already paid back out (withdrawals)') }}</dt><dd><x-usdt :micros="-$money['paid_out']" class="font-medium" /></dd></div>
                        <div class="flex justify-between gap-3 font-semibold"><dt>{{ __('Still in your wallets') }}</dt><dd><x-usdt :micros="$money['real_money_held']" data-real-money /></dd></div>
                    </dl>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <p class="text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-400">{{ __('Added by the platform') }}</p>
                    <dl class="mt-2 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3"><dt>{{ __('Admin adjustments') }}</dt><dd><x-usdt :micros="$money['adjustments']" signed class="font-medium" data-adjustments /></dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Plan earnings paid') }}</dt><dd><x-usdt :micros="$money['earnings']" class="font-medium" /></dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Referral bonuses') }}</dt><dd><x-usdt :micros="$money['bonuses']" class="font-medium" /></dd></div>
                    </dl>
                </div>
            </div>

            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-white/5">
                    <dt class="text-zinc-500">{{ __('Owed to customers (balances, active plans, withdrawals on hold)') }}</dt>
                    <dd class="mt-1 text-lg font-semibold [overflow-wrap:anywhere] text-zinc-900 dark:text-white"><x-usdt :micros="$money['owed_to_customers']" /></dd>
                </div>
                <div class="rounded-xl bg-zinc-50 p-4 dark:bg-white/5">
                    <dt class="text-zinc-500">{{ __('Shortfall if everyone withdrew today') }}</dt>
                    @php($shortfall = max(0, $money['owed_to_customers'] - $money['real_money_held']))
                    <dd @class(['mt-1 text-lg font-semibold [overflow-wrap:anywhere]', 'text-red-600 dark:text-red-400' => $shortfall > 0, 'text-green-600 dark:text-green-400' => $shortfall === 0])><x-usdt :micros="$shortfall" data-shortfall /></dd>
                </div>
            </dl>
        </flux:card>

        <livewire:pages::admin.bybit-account lazy />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card class="xl:col-span-2">
            <flux:heading>{{ __('Approved deposits, last 14 days') }}</flux:heading>
            @php($depositsTotal = collect($this->depositsByDay)->sum('value'))
            @if ($depositsTotal > 0)
                <flux:text class="mt-1 text-sm">{{ __('Total: :amount USDT', ['amount' => Usdt::format($depositsTotal)]) }}</flux:text>
            @endif
            <x-chart.line class="mt-6" :points="$depositsTotal > 0 ? $this->depositsByDay : []" :empty="__('No deposits were approved in the last 14 days.')" />
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <flux:heading>{{ __('Latest balance changes') }}</flux:heading>
                <flux:link :href="route('admin.ledger')" wire:navigate class="text-sm">{{ __('Audit log') }}</flux:link>
            </div>
            <ul class="mt-3 divide-y divide-zinc-100 text-sm dark:divide-white/10">
                @forelse ($this->recent as $entry)
                    <li wire:key="recent-{{ $entry->id }}" class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $entry->user->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $entry->type->label() }} · {{ $entry->created_at->diffForHumans() }}</p>
                        </div>
                        @if ($entry->type === LedgerType::Conversion)
                            <span class="text-end text-xs text-zinc-500">{{ __('Balance') }}<br><x-usdt :micros="$entry->balance_after_micros" class="text-sm text-zinc-900 dark:text-white" /></span>
                        @else
                            <x-usdt :micros="$entry->amount_micros" signed @class(['text-green-600 dark:text-green-400' => $entry->amount_micros > 0]) />
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">{{ __('Nothing yet.') }}</li>
                @endforelse
            </ul>
            @if ($this->recent->hasPages())
                <flux:pagination :paginator="$this->recent" class="mt-3 border-t border-zinc-100 pt-3 dark:border-white/10" />
            @endif
        </flux:card>
    </div>
</div>
