<?php

use App\Exceptions\WalletException;
use App\Models\Plan;
use App\Services\WalletService;
use App\Support\Btc;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plans')] class extends Component {
    public ?int $planId = null;

    public string $amount = '';

    public bool $acceptRisk = false;

    public bool $showModal = false;

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->available()->get();
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
        Flux::toast(variant: 'success', text: __('You joined :plan (demo).', ['plan' => $plan->name]));
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
        <flux:callout.text>{{ __('Targets are indicative and used to simulate earnings in this demo. Returns are not guaranteed and your capital is at risk. Money in a plan is locked until the end of its term.') }}</flux:callout.text>
    </flux:callout>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->plans as $plan)
            <flux:card wire:key="plan-{{ $plan->id }}" class="flex flex-col">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:badge size="sm" :color="$plan->category->value === 'gold' ? 'yellow' : 'orange'">{{ $plan->category->label() }}</flux:badge>
                        <flux:heading size="lg" class="mt-2">{{ $plan->name }}</flux:heading>
                    </div>
                    <flux:badge size="sm" :color="$plan->risk_level === 'medium' ? 'amber' : 'red'">{{ __(':level risk', ['level' => $plan->riskLabel()]) }}</flux:badge>
                </div>
                <flux:text class="mt-2 text-sm">{{ $plan->summary }}</flux:text>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-zinc-500">{{ __('Term') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">{{ trans_choice(':count day|:count days', $plan->duration_days) }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Indicative target') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">{{ $plan->targetPercent() }}% {{ __('over term') }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Minimum') }}</dt><dd class="font-medium text-zinc-900 dark:text-white"><x-btc :sats="$plan->min_sats" /></dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Maximum') }}</dt><dd class="font-medium text-zinc-900 dark:text-white">@if ($plan->max_sats)<x-btc :sats="$plan->max_sats" />@else{{ __('No limit') }}@endif</dd></div>
                </dl>
                <div class="mt-auto pt-5">
                    <flux:button wire:click="choose({{ $plan->id }})" variant="primary" class="w-full">{{ __('Invest (demo)') }}</flux:button>
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
                    <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-white/5">
                        <div class="flex justify-between"><span>{{ __('If the target were reached') }}</span><x-btc :sats="intdiv($sats * $plan->target_return_bps, 10000)" signed class="font-medium" /></div>
                        <p class="mt-1 text-xs text-zinc-500">{{ __('Illustration only, not a promise. The real result could be lower or a loss.') }}</p>
                    </div>
                @endif

                <flux:field variant="inline">
                    <flux:checkbox wire:model="acceptRisk" />
                    <flux:label class="text-sm leading-snug">{{ __('I understand returns are not guaranteed, my money is locked for the term, and this is a demo.') }}</flux:label>
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
