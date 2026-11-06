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

    #[Url]
    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
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
            ->when($this->status === 'banned', fn ($query) => $query->whereNotNull('banned_at'))
            ->when($this->status === 'suspended', fn ($query) => $query->whereNull('banned_at')->where('is_suspended', true)
                ->where(fn ($inner) => $inner->whereNull('suspended_until')->orWhere('suspended_until', '>', now())))
            ->when($this->status === 'active', fn ($query) => $query->whereNull('banned_at')
                ->where(fn ($inner) => $inner->where('is_suspended', false)->orWhere('suspended_until', '<=', now())))
            ->latest()
            ->paginate(20);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <flux:heading size="xl">{{ __('Users') }}</flux:heading>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <flux:select wire:model.live="status" class="sm:max-w-40">
                <flux:select.option value="all">{{ __('All users') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
                <flux:select.option value="banned">{{ __('Banned') }}</flux:select.option>
            </flux:select>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email or code')" class="sm:max-w-72" />
        </div>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->users" class="[&_td]:px-4! [&_th]:px-4!">
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
                                <x-account-status-badge :user="$user" class="ms-1" />
                            </p>
                            <p class="text-xs text-zinc-500">{{ $user->email }}</p>
                        </flux:table.cell>
                        <flux:table.cell><x-usdt :micros="$user->balance_micros" /></flux:table.cell>
                        <flux:table.cell>
                            @if ($user->kycStatus())
                                <flux:badge size="sm" :color="$user->kycStatus()->color()">{{ $user->kycStatus()->label() }}</flux:badge>
                            @else
                                <span class="text-xs text-zinc-400">{{ __('Not sent') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $user->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" :href="route('admin.users.show', $user)" wire:navigate>{{ __('Open') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center">{{ __('No users found.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
