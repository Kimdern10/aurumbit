<?php

use App\Enums\InvestmentStatus;
use App\Enums\LedgerType;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\AdminCounts;
use App\Support\Btc;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin overview')] class extends Component {
    /**
     * @return array{users: int, balances: int, invested: int, active: int}
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'users' => User::query()->where('is_admin', false)->count(),
            'balances' => (int) User::query()->sum('balance_sats'),
            'invested' => (int) Investment::query()->where('status', InvestmentStatus::Active)->sum('amount_sats'),
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
            ->get(['amount_sats', 'created_at'])
            ->groupBy(fn (LedgerEntry $entry): string => $entry->created_at->format('Y-m-d'))
            ->map(fn ($entries): int => (int) $entries->sum('amount_sats'));

        return collect(range(0, 13))->map(function (int $offset) use ($since, $sums): array {
            $day = $since->addDays($offset);
            $value = $sums[$day->format('Y-m-d')] ?? 0;

            return ['label' => $day->format('M j'), 'value' => $value, 'text' => Btc::format($value).' BTC'];
        })->all();
    }

    /**
     * @return Collection<int, LedgerEntry>
     */
    #[Computed]
    public function recent(): Collection
    {
        return LedgerEntry::query()->with('user:id,name')->latest('id')->limit(8)->get();
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
            <a href="{{ route($queue['route']) }}" wire:navigate.hover class="group flex items-center justify-between rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-gold-400 dark:border-white/10 dark:bg-navy-900">
                <div>
                    <flux:text class="text-sm">{{ $queue['label'] }}</flux:text>
                    <p class="mt-1 font-display text-2xl font-semibold text-zinc-900 dark:text-white">{{ $queue['count'] }}</p>
                </div>
                <flux:icon :name="$queue['icon']" @class(['size-8', 'text-gold-500' => $queue['count'] > 0, 'text-zinc-300 dark:text-zinc-600' => $queue['count'] === 0]) />
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Users')" icon="users">{{ number_format($this->totals['users']) }}</x-stat-card>
        <x-stat-card :label="__('Customer balances')" icon="wallet"><x-btc :sats="$this->totals['balances']" /></x-stat-card>
        <x-stat-card :label="__('In active plans')" icon="chart-pie"><x-btc :sats="$this->totals['invested']" /></x-stat-card>
        <x-stat-card :label="__('Active investments')" icon="sparkles">{{ number_format($this->totals['active']) }}</x-stat-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card class="xl:col-span-2">
            <flux:heading>{{ __('Approved deposits, last 14 days') }}</flux:heading>
            <x-chart.line class="mt-6" :points="$this->depositsByDay" />
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
                        <x-btc :sats="$entry->amount_sats" signed />
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">{{ __('Nothing yet.') }}</li>
                @endforelse
            </ul>
        </flux:card>
    </div>
</div>
