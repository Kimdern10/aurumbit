<?php

use App\Enums\PlanCategory;
use App\Exceptions\WalletException;
use App\Models\Plan;
use App\Services\WalletService;
use App\Support\Usdt;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Plans')] class extends Component {
    use WithPagination;

    public ?int $planId = null;

    public string $amount = '';

    public bool $acceptRisk = false;

    public bool $showModal = false;

    #[Url]
    public string $category = '';

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->available()->get();
    }

    /**
     * One tab per category that has open plans, after "All plans".
     *
     * @return array<string, string>
     */
    #[Computed]
    public function tabs(): array
    {
        $tabs = ['' => __('All plans')];

        foreach (PlanCategory::cases() as $case) {
            if ($this->plans->contains('category', $case)) {
                $tabs[$case->value] = $case->label();
            }
        }

        return $tabs;
    }

    /**
     * The plans in the chosen tab, 9 per page.
     *
     * @return LengthAwarePaginator<int, Plan>
     */
    #[Computed]
    public function shownPlans(): LengthAwarePaginator
    {
        return Plan::query()->available()
            ->when(PlanCategory::tryFrom($this->category), fn ($query, PlanCategory $category) => $query->where('category', $category))
            ->paginate(9);
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function selectedPlan(): ?Plan
    {
        return $this->planId ? $this->plans->firstWhere('id', $this->planId) : null;
    }

    public function choose(int $planId): void
    {
        $this->resetValidation();
        $this->planId = $planId;
        $this->amount = $this->selectedPlan ? Usdt::format($this->selectedPlan->min_micros) : '';
        $this->acceptRisk = false;
        $this->showModal = true;
    }

    public function invest(WalletService $wallet): void
    {
        $plan = $this->selectedPlan;
        abort_if($plan === null, 404);

        $this->validate([
            'amount' => ['required', 'regex:'.Usdt::INPUT_PATTERN],
            'acceptRisk' => ['accepted'],
        ], [
            'amount.regex' => __('Enter an amount in USDT, with up to 2 decimal places.'),
            'acceptRisk.accepted' => __('Please confirm before investing.'),
        ]);

        try {
            $wallet->invest(Auth::user(), $plan, (int) Usdt::toMicros($this->amount));
        } catch (WalletException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        Flux::toast(variant: 'success', text: __('You joined :plan.', ['plan' => $plan->name]));
        $this->redirectRoute('investments', navigate: true);
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Available balance:') }} <x-usdt :micros="auth()->user()->balance_micros" class="font-semibold text-zinc-900 dark:text-white" /></flux:text>
    </div>

    <flux:callout color="amber" icon="exclamation-triangle" data-risk-notice>
        <flux:callout.heading>{{ __('Rates are targets, not guarantees') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Earnings build up every day at the plan\'s target rate and are paid with your capital at the end of the term. Crypto-asset plans carry risk: you could earn less than the target or lose money. Only invest what you can afford to lose.') }}
            <flux:link :href="route('legal.risk')" wire:navigate>{{ __('Read the risk disclosure') }}</flux:link>
        </flux:callout.text>
    </flux:callout>

    @if ($this->plans->isNotEmpty())
        <div>
            <flux:heading>{{ __('How much will I earn?') }}</flux:heading>
            <x-returns-calculator class="mt-3" :plans="$this->plans" />
        </div>
    @endif

    <x-coin-converter />

    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($this->tabs as $value => $label)
            <button type="button" wire:click="$set('category', '{{ $value }}')" wire:key="tab-{{ $value ?: 'all' }}"
                @class(['shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition', 'border-brand-500 bg-brand-500 text-white dark:text-ink-950' => $category === $value, 'border-zinc-200 bg-white hover:border-brand-400 dark:border-white/10 dark:bg-ink-900' => $category !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-prices-url="{{ route('prices') }}">
        @forelse ($this->shownPlans as $plan)
            <flux:card wire:key="plan-{{ $plan->id }}" class="flex flex-col">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:badge size="sm" :color="$plan->category->badgeColor()">{{ $plan->category->label() }}</flux:badge>
                        <flux:heading size="lg" class="mt-2">{{ $plan->name }}</flux:heading>
                        <x-coin-price :category="$plan->category" class="mt-1" />
                    </div>
                    <span class="text-right"><span class="block font-display text-2xl font-extrabold text-zinc-900 dark:text-white">{{ $plan->targetPercent() }}%</span><span class="text-xs text-zinc-500">{{ __('in :days days', ['days' => $plan->duration_days]) }}</span></span>
                </div>
                <flux:text class="mt-2 text-sm">{{ $plan->summary }}</flux:text>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-zinc-500">{{ __('Term') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">{{ trans_choice(':count day|:count days', $plan->duration_days) }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Rate') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->targetPercent() }}% <span class="text-xs font-normal text-zinc-500">({{ $plan->dailyPercent() }}% {{ __('a day') }})</span></dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Minimum') }}</dt><dd class="font-medium text-zinc-900 dark:text-white"><x-usdt :micros="$plan->min_micros" /></dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Maximum') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">@if ($plan->max_micros)<x-usdt :micros="$plan->max_micros" />@else{{ __('No limit') }}@endif</dd></div>
                </dl>
                @php
                    $example = min(max($plan->min_micros, 1_000 * Usdt::MICROS_PER_USDT), $plan->max_micros ?? PHP_INT_MAX);
                    $exampleTotal = $example + $plan->profitFor($example);
                    $examplePrice = $plan->category->priceKey() && $plan->category !== PlanCategory::Gold ? app(\App\Services\MarketPrices::class)->cached()[$plan->category->priceKey().'_cents'] : null;
                @endphp
                <p class="mt-4 rounded-lg bg-zinc-50 px-3 py-2 text-xs leading-relaxed text-zinc-600 dark:bg-white/5 dark:text-zinc-300" data-plan-example>
                    {{ __('Example: :amount USDT', ['amount' => Usdt::format($example)]) }}@if ($examplePrice) {{ __('(≈ :coin :ticker today)', ['coin' => \App\Support\Btc::format(Usdt::toCoinUnits($example, $examplePrice), fixed: true), 'ticker' => $plan->category->ticker()]) }}@endif
                    {{ __('returns :total USDT after :days days', ['total' => Usdt::format($exampleTotal), 'days' => $plan->duration_days]) }}@if ($examplePrice), {{ __('or ≈ :coin :ticker if paid in :ticker', ['coin' => \App\Support\Btc::format(Usdt::toCoinUnits($exampleTotal, $examplePrice), fixed: true), 'ticker' => $plan->category->ticker()]) }}@endif.
                </p>
                <div class="mt-auto pt-5">
                    <flux:button wire:click="choose({{ $plan->id }})" variant="primary" class="w-full">{{ __('Invest') }}</flux:button>
                </div>
            </flux:card>
        @empty
            <flux:text>{{ __('No plans are open right now.') }}</flux:text>
        @endforelse
    </div>

    @if ($this->shownPlans->hasPages())
        <flux:pagination :paginator="$this->shownPlans" />
    @endif

    <flux:modal wire:model.self="showModal" class="w-full max-w-md">
        @if ($this->selectedPlan)
            @php
                $plan = $this->selectedPlan;
                $micros = Usdt::toMicros($amount);
            @endphp
            <form wire:submit="invest" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ trans_choice(':count day term|:count days term', $plan->duration_days) }} · {{ __(':rate% total', ['rate' => $plan->targetPercent()]) }}</flux:text>
                    <x-coin-price :category="$plan->category" class="mt-1" />
                </div>

                <flux:input wire:model.live.debounce.400ms="amount" :label="__('Amount (USDT)')" inputmode="decimal" autocomplete="off"
                    :description="__('Between :min and :max USDT. Available: :balance USDT', ['min' => Usdt::format($plan->min_micros), 'max' => $plan->max_micros ? Usdt::format($plan->max_micros) : '∞', 'balance' => Usdt::format(auth()->user()->balance_micros)])" />

                @if ($micros)
                    @php($profit = $plan->profitFor($micros))
                    <dl class="space-y-1.5 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-white/5">
                        <div class="flex justify-between"><dt>{{ __('Rate') }}</dt><dd>{{ $plan->targetPercent() }}% {{ __('for :days days', ['days' => $plan->duration_days]) }}</dd></div>
                        <div class="flex justify-between"><dt>{{ __('Earned per day') }}</dt><dd><x-usdt :micros="intdiv($profit, $plan->duration_days)" /></dd></div>
                        <div class="flex justify-between"><dt>{{ __('Profit at the end') }}</dt><dd><x-usdt :micros="$profit" signed class="text-green-600 dark:text-green-400" /></dd></div>
                        <div class="flex justify-between border-t border-zinc-200 pt-1.5 font-semibold text-zinc-900 dark:border-white/10 dark:text-white"><dt>{{ __('You receive on :date', ['date' => now()->addDays($plan->duration_days)->format('M j, Y')]) }}</dt><dd><x-usdt :micros="$micros + $profit" /></dd></div>
                    </dl>
                    @php($coinPriceCents = $plan->category->priceKey() && $plan->category !== PlanCategory::Gold ? app(\App\Services\MarketPrices::class)->cached()[$plan->category->priceKey().'_cents'] : null)
                    @if ($coinPriceCents)
                        <flux:text class="text-sm">{{ __('At today\'s price you receive about :coin :ticker. You choose USDT or :ticker when you withdraw.', ['coin' => \App\Support\Btc::format(Usdt::toCoinUnits($micros + $profit, $coinPriceCents)), 'ticker' => $plan->category->ticker()]) }}</flux:text>
                    @endif
                    @if ($plan->category === PlanCategory::Gold && ($goldMg = app(\App\Services\MarketPrices::class)->goldMilligrams($micros)))
                        <flux:text class="text-sm">{{ __('Your :amount USDT is worth about :ounces oz (:grams g) of gold at today\'s price.', ['amount' => Usdt::format($micros), 'ounces' => number_format($goldMg / 31103.4768, 3), 'grams' => number_format($goldMg / 1000, 2)]) }}</flux:text>
                    @endif
                @endif

                <flux:field variant="inline">
                    <flux:checkbox wire:model="acceptRisk" />
                    <flux:label class="text-sm leading-snug">{{ __('I understand the rate is a target, not a guarantee, and my money stays in this plan until the end of the term.') }}</flux:label>
                    <flux:error name="acceptRisk" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Confirm') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
