<?php

use App\Models\Investment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My investments')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, Investment>
     */
    #[Computed]
    public function investments(): LengthAwarePaginator
    {
        return Auth::user()->investments()->with('plan')->latest('starts_at')->paginate(10);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My investments') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Earnings update daily and are paid with your capital at the end of each term.') }}</flux:text>
        </div>
        <flux:button :href="route('invest')" variant="primary" icon="sparkles" wire:navigate>{{ __('New investment') }}</flux:button>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->investments" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Progress') }}</flux:table.column>
                <flux:table.column>{{ __('Earnings') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->investments as $investment)
                    <flux:table.row :key="$investment->id">
                        <flux:table.cell>
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $investment->plan->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $investment->starts_at->format('M j, Y') }} → {{ $investment->ends_at->format('M j, Y') }}</p>
                            @if ($investment->gold_mg)
                                <p class="text-xs text-gold-700 dark:text-gold-300">{{ __('≈ :grams g of gold at start', ['grams' => number_format($investment->gold_mg / 1000, 3)]) }}</p>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><x-btc :sats="$investment->amount_sats" /></flux:table.cell>
                        <flux:table.cell class="min-w-32">
                            <flux:progress :value="$investment->status->value === 'active' ? $investment->progressPercent() : 100" />
                            <p class="mt-1 text-xs text-zinc-500">{{ __('Day :day of :total', ['day' => $investment->elapsedDays(), 'total' => $investment->duration_days]) }}</p>
                        </flux:table.cell>
                        <flux:table.cell><x-btc :sats="$investment->earned_sats" /></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$investment->status->color()">{{ $investment->status->label() }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">{{ __('You have not joined a plan yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
