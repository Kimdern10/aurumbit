<?php

use App\Enums\InvestmentStatus;
use App\Exceptions\WalletException;
use App\Models\Investment;
use App\Services\WalletService;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Investments')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'active';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Investment>
     */
    #[Computed]
    public function investments(): LengthAwarePaginator
    {
        return Investment::query()->with(['user:id,name', 'plan:id,name'])
            ->when(InvestmentStatus::tryFrom($this->status), fn ($query, InvestmentStatus $status) => $query->where('status', $status))
            ->orderBy('ends_at')
            ->paginate(20);
    }

    /**
     * Pay out now: principal plus earnings so far (or to the end if finished).
     */
    public function settle(int $investmentId, WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $this->run(fn () => $wallet->settle(Investment::query()->findOrFail($investmentId), Auth::user()), __('Investment settled.'));
    }

    /**
     * Stop early and return only the principal.
     */
    public function cancel(int $investmentId, WalletService $wallet): void
    {
        Gate::authorize('manage-money');

        $this->run(fn () => $wallet->cancel(Investment::query()->findOrFail($investmentId), Auth::user()), __('Investment cancelled and principal returned.'));
    }

    private function run(Closure $action, string $message): void
    {
        try {
            $action();
            Flux::toast(variant: 'success', text: $message);
        } catch (WalletException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }

        unset($this->investments);
    }
};
?>

@php($canManageMoney = auth()->user()->can('manage-money'))

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Investments') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Finished investments settle automatically each hour (scheduled task). You can also settle or cancel by hand.') }}</flux:text>
        </div>
        <flux:select wire:model.live="status" class="sm:max-w-48">
            @foreach (InvestmentStatus::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->investments" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('User / plan') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Term') }}</flux:table.column>
                <flux:table.column>{{ __('Earnings') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->investments as $investment)
                    <flux:table.row :key="$investment->id">
                        <flux:table.cell>
                            <a href="{{ route('admin.users.show', $investment->user_id) }}" wire:navigate class="font-medium text-zinc-900 hover:underline dark:text-white">{{ $investment->user->name }}</a>
                            <p class="text-xs text-zinc-500">{{ $investment->plan->name }}</p>
                        </flux:table.cell>
                        <flux:table.cell><x-usdt :micros="$investment->amount_micros" /></flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-xs">{{ $investment->starts_at->format('M j') }} → {{ $investment->ends_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell><x-usdt :micros="$investment->earnedSoFar()" :decimals="4" /></flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$investment->status->color()">{{ $investment->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($investment->status === InvestmentStatus::Active && $canManageMoney)
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" wire:click="settle({{ $investment->id }})" wire:confirm="{{ __('Pay out the principal and earnings so far?') }}">{{ __('Settle') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="cancel({{ $investment->id }})" wire:confirm="{{ __('Cancel and return only the principal?') }}">{{ __('Cancel') }}</flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="6" class="text-center">{{ __('Nothing here.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
