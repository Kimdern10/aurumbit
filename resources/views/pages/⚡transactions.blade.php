<?php

use App\Enums\LedgerType;
use App\Models\LedgerEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Transactions')] class extends Component {
    use WithPagination;

    #[Url]
    public string $type = '';

    public function updatedType(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return Auth::user()->ledgerEntries()
            ->when(LedgerType::tryFrom($this->type), fn ($query, LedgerType $type) => $query->where('type', $type))
            ->latest('id')
            ->paginate(15);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Transactions') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Every change to your balance, with the balance after it.') }}</flux:text>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
        <flux:button :href="route('transactions.export')" icon="arrow-down-tray">{{ __('Download statement (CSV)') }}</flux:button>
        <flux:select wire:model.live="type" class="sm:max-w-56">
            <flux:select.option value="">{{ __('All types') }}</flux:select.option>
            @foreach (LedgerType::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        </div>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->entries" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Details') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Balance after') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->entries as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $entry->created_at->format('j M Y, H:i') }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$entry->amount_sats >= 0 ? 'green' : 'zinc'">{{ $entry->type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="max-w-72 truncate">{{ $entry->description }}</flux:table.cell>
                        <flux:table.cell align="end"><x-btc :sats="$entry->amount_sats" signed @class(['font-medium', 'text-green-600 dark:text-green-400' => $entry->amount_sats > 0]) /></flux:table.cell>
                        <flux:table.cell align="end"><x-btc :sats="$entry->balance_after_sats" /></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">{{ __('No transactions yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
