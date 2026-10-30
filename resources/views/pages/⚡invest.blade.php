<?php

use App\Enums\PlanCategory;
use App\Exceptions\WalletException;
use App\Models\Plan;
use App\Services\WalletService;
use App\Support\Btc;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Plans')] class extends Component {
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
     * The plans in the chosen tab.
     *
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function shownPlans(): Collection
    {
        $category = PlanCategory::tryFrom($this->category);

        return $category ? $this->plans->where('category', $category)->values() : $this->plans;
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
        $this->amount = $this->selectedPlan ? Btc::format($this->selectedPlan->min_sats) : '';
        $this->acceptRisk = false;
        $this->showModal = true;
    }

    public function invest(WalletService $wallet): void
    {
        $plan = $this->selectedPlan;
        abort_if($plan === null, 404);

        $this->validate([
            'amount' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'acceptRisk' => ['accepted'],
        ], [
            'amount.regex' => __('Enter an amount in BTC, with up to 8 decimal places.'),
            'acceptRisk.accepted' => __('Please confirm you understand the risks.'),
        ]);

        try {
            $wallet->invest(Auth::user(), $plan, (int) Btc::toSats($this->amount));
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
        <flux:text class="mt-1">{{ __('Available balance:') }} <x-btc :sats="auth()->user()->balance_sats" class="font-semibold text-zinc-900 dark:text-white" /></flux:text>
    </div>

    <flux:callout color="amber" icon="exclamation-triangle">
        <flux:callout.text>{{ __('Earnings build up every day at the plan rate and are paid with your capital at the end of the term. Money in a plan is locked until then. Investing carries risk.') }}</flux:callout.text>
    </flux:callout>

    @if ($this->plans->isNotEmpty())
        <div>
            <flux:heading>{{ __('How much will I earn?') }}</flux:heading>
            <x-returns-calculator class="mt-3" :plans="$this->plans" />
        </div>
    @endif

    <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        @foreach ($this->tabs as $value => $label)
            <button type="button" wire:click="$set('category', '{{ $value }}')" wire:key="tab-{{ $value ?: 'all' }}"
                @class(['shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition', 'border-gold-500 bg-gold-500 text-white dark:text-navy-950' => $category === $value, 'border-zinc-200 bg-white hover:border-gold-400 dark:border-white/10 dark:bg-navy-900' => $category !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->shownPlans as $plan)
            <flux:card wire:key="plan-{{ $plan->id }}" class="flex flex-col">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:badge size="sm" :color="$plan->category->badgeColor()">{{ $plan->category->label() }}</flux:badge>
                        <flux:heading size="lg" class="mt-2">{{ $plan->name }}</flux:heading>
                    </div>
                    <flux:badge size="sm" :color="$plan->risk_level === 'medium' ? 'amber' : 'red'">{{ __(':level risk', ['level' => $plan->riskLabel()]) }}</flux:badge>
                </div>
                <flux:text class="mt-2 text-sm">{{ $plan->summary }}</flux:text>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-zinc-500">{{ __('Term') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">{{ trans_choice(':count day|:count days', $plan->duration_days) }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Rate') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->targetPercent() }}% <span class="text-xs font-normal text-zinc-500">({{ $plan->dailyPercent() }}% {{ __('a day') }})</span></dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Minimum') }}</dt><dd class="font-medium text-zinc-900 dark:text-white"><x-btc :sats="$plan->min_sats" /></dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Maximum') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">@if ($plan->max_sats)<x-btc :sats="$plan->max_sats" />@else{{ __('No limit') }}@endif</dd></div>
                </dl>
                <div class="mt-auto pt-5">
                    <flux:button wire:click="choose({{ $plan->id }})" variant="primary" class="w-full">{{ __('Invest') }}</flux:button>
                </div>
            </flux:card>
        @empty
            <flux:text>{{ __('No plans are open right now.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal wire:model.self="showModal" class="w-full max-w-md">
        @if ($this->selectedPlan)
            @php
                $plan = $this->selectedPlan;
                $sats = Btc::toSats($amount);
            @endphp
            <form wire:submit="invest" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ trans_choice(':count day term|:count days term', $plan->duration_days) }} · {{ __(':level risk', ['level' => $plan->riskLabel()]) }}</flux:text>
                </div>

                <flux:input wire:model.live.debounce.400ms="amount" :label="__('Amount (BTC)')" inputmode="decimal" autocomplete="off"
                    :description="__('Between :min and :max BTC. Available: :balance BTC', ['min' => Btc::format($plan->min_sats), 'max' => $plan->max_sats ? Btc::format($plan->max_sats) : '∞', 'balance' => Btc::format(auth()->user()->balance_sats)])" />

                @if ($sats)
                    @php($profit = $plan->profitFor($sats))
                    <dl class="space-y-1.5 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-white/5">
                        <div class="flex justify-between"><dt>{{ __('Rate') }}</dt><dd>{{ $plan->targetPercent() }}% {{ __('for :days days', ['days' => $plan->duration_days]) }}</dd></div>
                        <div class="flex justify-between"><dt>{{ __('Earned per day') }}</dt><dd><x-btc :sats="intdiv($profit, $plan->duration_days)" /></dd></div>
                        <div class="flex justify-between"><dt>{{ __('Profit at the end') }}</dt><dd><x-btc :sats="$profit" signed class="text-green-600 dark:text-green-400" /></dd></div>
                        <div class="flex justify-between border-t border-zinc-200 pt-1.5 font-semibold text-zinc-900 dark:border-white/10 dark:text-white"><dt>{{ __('You receive on :date', ['date' => now()->addDays($plan->duration_days)->format('M j, Y')]) }}</dt><dd><x-btc :sats="$sats + $profit" /></dd></div>
                    </dl>
                    @if ($plan->category === PlanCategory::Gold && ($goldMg = app(\App\Services\MarketPrices::class)->goldMilligrams($sats)))
                        <flux:text class="text-sm">{{ __('Your :amount BTC is worth about :ounces oz (:grams g) of gold at today\'s price.', ['amount' => Btc::format($sats), 'ounces' => number_format($goldMg / 31103.4768, 3), 'grams' => number_format($goldMg / 1000, 2)]) }}</flux:text>
                    @endif
                @endif

                <flux:field variant="inline">
                    <flux:checkbox wire:model="acceptRisk" />
                    <flux:label class="text-sm leading-snug">{{ __('I understand my money is locked until the end of the term and that investing carries risk.') }}</flux:label>
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
