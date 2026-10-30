<?php

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('latestKyc')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')
                ->orWhere('referral_code', $this->search)))
            ->latest()
            ->paginate(20);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email or code')" class="sm:max-w-72" />
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->users" class="[&_td]:px-4 [&_th]:px-4">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Balance') }}</flux:table.column>
                <flux:table.column>{{ __('Identity') }}</flux:table.column>
                <flux:table.column>{{ __('Joined') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $user->name }}
                                @if ($user->is_admin) <flux:badge size="sm" color="yellow" class="ms-1">{{ __('Admin') }}</flux:badge> @endif
                                @if ($user->is_suspended) <flux:badge size="sm" color="red" class="ms-1">{{ __('Suspended') }}</flux:badge> @endif
                            </p>
                            <p class="text-xs text-zinc-500">{{ $user->email }}</p>
                        </flux:table.cell>
                        <flux:table.cell><x-btc :sats="$user->balance_sats" /></flux:table.cell>
                        <flux:table.cell>
                            @if ($user->kycStatus())
                                <flux:badge size="sm" :color="$user->kycStatus()->color()">{{ $user->kycStatus()->label() }}</flux:badge>
                            @else
                                <span class="text-xs text-zinc-400">{{ __('Not sent') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $user->created_at->format('j M Y') }}</flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" :href="route('admin.users.show', $user)" wire:navigate>{{ __('Open') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center">{{ __('No users found.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
