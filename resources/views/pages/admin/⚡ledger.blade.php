<?php

use App\Enums\LedgerType;
use App\Models\LedgerEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit log')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, LedgerEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return LedgerEntry::query()
            ->with(['user:id,name,email', 'actor:id,name'])
            ->when(LedgerType::tryFrom($this->type), fn ($query, LedgerType $type) => $query->where('type', $type))
            ->when($this->search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->latest('id')
            ->paginate(25);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Audit log') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Every balance change on the platform. Entries can never be edited or removed from the admin.') }}</flux:text>
        </div>
        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('User name or email')" />
            <flux:select wire:model.live="type">
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
                <flux:table.column>#</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Change') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Balance after') }}</flux:table.column>
                <flux:table.column>{{ __('By') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->entries as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell class="text-xs text-zinc-500">{{ $entry->id }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $entry->created_at->format('M j, Y g:i:s A') }}</flux:table.cell>
                        <flux:table.cell>
                            <a href="{{ route('admin.users.show', $entry->user_id) }}" wire:navigate class="hover:underline">{{ $entry->user->name }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            <p class="font-medium">{{ $entry->type->label() }}</p>
                            <p class="max-w-72 truncate text-xs text-zinc-500">{{ $entry->description }}</p>
                        </flux:table.cell>
                        <flux:table.cell align="end"><x-btc :sats="$entry->amount_sats" signed /></flux:table.cell>
                        <flux:table.cell align="end"><x-btc :sats="$entry->balance_after_sats" /></flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $entry->actor?->name ?? __('User / system') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="7" class="text-center">{{ __('No entries.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
