<?php

use App\Enums\PlanCategory;
use App\Models\Plan;
use App\Support\Btc;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plans')] class extends Component {
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $category = 'bitcoin';

    public string $summary = '';

    public string $minBtc = '0.001';

    public string $maxBtc = '';

    public int|string $durationDays = 30;

    public string $targetPercent = '2';

    public string $riskLevel = 'high';

    public bool $isActive = true;

    public int|string $sortOrder = 0;

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->withCount(['investments as active_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'category', 'summary', 'minBtc', 'maxBtc', 'durationDays', 'targetPercent', 'riskLevel', 'isActive', 'sortOrder');
        $this->showForm = true;
    }

    public function edit(Plan $plan): void
    {
        $this->resetValidation();
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->category = $plan->category->value;
        $this->summary = $plan->summary;
        $this->minBtc = Btc::format($plan->min_sats);
        $this->maxBtc = $plan->max_sats ? Btc::format($plan->max_sats) : '';
        $this->durationDays = $plan->duration_days;
        $this->targetPercent = $plan->targetPercent();
        $this->riskLevel = $plan->risk_level;
        $this->isActive = $plan->is_active;
        $this->sortOrder = $plan->sort_order;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::enum(PlanCategory::class)],
            'summary' => ['required', 'string', 'max:500'],
            'minBtc' => ['required', 'regex:'.Btc::INPUT_PATTERN],
            'maxBtc' => ['nullable', 'regex:'.Btc::INPUT_PATTERN],
            'durationDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'targetPercent' => ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'riskLevel' => ['required', Rule::in(array_keys(Plan::RISK_LEVELS))],
            'isActive' => ['boolean'],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $min = (int) Btc::toSats($validated['minBtc']);
        $max = filled($validated['maxBtc']) ? Btc::toSats($validated['maxBtc']) : null;

        if ($min < 1 || ($max !== null && $max < $min)) {
            $this->addError('maxBtc', __('The maximum must be at least the minimum.'));

            return;
        }

        [$whole, $fraction] = array_pad(explode('.', $validated['targetPercent']), 2, '');

        $attributes = [
            'name' => $validated['name'],
            'category' => $validated['category'],
            'summary' => $validated['summary'],
            'min_sats' => $min,
            'max_sats' => $max,
            'duration_days' => (int) $validated['durationDays'],
            'target_return_bps' => (int) $whole * 100 + (int) str_pad($fraction, 2, '0'),
            'risk_level' => $validated['riskLevel'],
            'is_active' => $validated['isActive'],
            'sort_order' => (int) $validated['sortOrder'],
        ];

        if ($this->editingId) {
            Plan::query()->findOrFail($this->editingId)->update($attributes);
        } else {
            Plan::query()->create([...$attributes, 'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(4))]);
        }

        $this->showForm = false;
        unset($this->plans);
        Flux::toast(variant: 'success', text: __('Plan saved. Running investments keep their original terms.'));
    }

    /**
     * What a customer would earn on 0.1 BTC with the rate and term being typed.
     *
     * @return array{daily: int, profit: int, total: int, days: int}|null
     */
    public function preview(): ?array
    {
        $days = (int) $this->durationDays;

        if ($days < 1 || preg_match('/^\d{1,3}(\.\d{1,2})?$/', $this->targetPercent) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $this->targetPercent), 2, '');
        $profit = intdiv(10_000_000 * ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')), 10000);

        return ['daily' => intdiv($profit, $days), 'profit' => $profit, 'total' => 10_000_000 + $profit, 'days' => $days];
    }

    public function toggle(Plan $plan): void
    {
        $plan->update(['is_active' => ! $plan->is_active]);
        unset($this->plans);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Plans') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Changes apply to new investments only. Running investments keep the rate they started with.') }}</flux:text>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('New plan') }}</flux:button>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Limits') }}</flux:table.column>
                <flux:table.column>{{ __('Term / target') }}</flux:table.column>
                <flux:table.column>{{ __('Active investments') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->plans as $plan)
                    <flux:table.row :key="$plan->id">
                        <flux:table.cell>
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $plan->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $plan->category->label() }} · {{ __(':level risk', ['level' => $plan->riskLabel()]) }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs"><x-btc :sats="$plan->min_sats" /> – @if ($plan->max_sats)<x-btc :sats="$plan->max_sats" />@else ∞ @endif</flux:table.cell>
                        <flux:table.cell>{{ trans_choice(':count day|:count days', $plan->duration_days) }} · {{ $plan->targetPercent() }}%</flux:table.cell>
                        <flux:table.cell>{{ $plan->active_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:switch :checked="$plan->is_active" wire:click="toggle({{ $plan->id }})" :label="$plan->is_active ? __('Open') : __('Closed')" />
                        </flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" wire:click="edit({{ $plan->id }})" icon="pencil-square">{{ __('Edit') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="6" class="text-center">{{ __('No plans yet.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model.self="showForm" class="w-full max-w-xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit plan') : __('New plan') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" />
                <flux:select wire:model="category" :label="__('Category')">
                    @foreach (PlanCategory::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:textarea wire:model="summary" :label="__('Summary')" rows="3" :description="__('Describe what the plan invests in.')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="minBtc" :label="__('Minimum (BTC)')" inputmode="decimal" />
                <flux:input wire:model="maxBtc" :label="__('Maximum (BTC)')" inputmode="decimal" :placeholder="__('No limit')" />
                <flux:input wire:model.live.debounce.400ms="durationDays" type="number" min="1" :label="__('Term (days)')" />
                <flux:input wire:model.live.debounce.400ms="targetPercent" :label="__('Rate for the whole term (%)')" inputmode="decimal" :description="__('e.g. 3.5 means 3.5% profit after the full term, spread evenly per day.')" />
                <flux:select wire:model="riskLevel" :label="__('Risk level')">
                    @foreach (Plan::RISK_LEVELS as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="sortOrder" type="number" min="0" :label="__('Order')" />
            </div>
            @if ($preview = $this->preview())
                <div class="rounded-lg bg-gold-50 p-3 text-sm text-gold-900 dark:bg-gold-400/10 dark:text-gold-100">
                    <p class="font-medium">{{ __('Customer preview for 0.1 BTC') }}</p>
                    <p class="mt-1">{{ __(':daily BTC a day · +:profit BTC profit · :total BTC paid back after :days days', ['daily' => Btc::format($preview['daily']), 'profit' => Btc::format($preview['profit']), 'total' => Btc::format($preview['total']), 'days' => $preview['days']]) }}</p>
                </div>
            @endif
            <flux:switch wire:model="isActive" :label="__('Open for new investments')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save plan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
