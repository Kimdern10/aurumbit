<?php

use App\Models\SupportTicket;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Support')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'open';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SupportTicket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return SupportTicket::query()->with('user:id,name,email')
            ->when(array_key_exists($this->status, SupportTicket::STATUSES), fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('last_reply_at')
            ->paginate(20);
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Support tickets') }}</flux:heading>
            <flux:text class="mt-1">{{ __('"Waiting for support" tickets need a reply from you.') }}</flux:text>
        </div>
        <flux:select wire:model.live="status" class="sm:max-w-56">
            @foreach (SupportTicket::STATUSES as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:card class="!p-0 overflow-hidden">
        <flux:table :paginate="$this->tickets" class="[&_td]:px-4! [&_th]:px-4!">
            <flux:table.columns>
                <flux:table.column>{{ __('Ticket') }}</flux:table.column>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Last message') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell class="max-w-72"><span class="block truncate font-medium text-zinc-900 dark:text-white">#{{ $ticket->id }} · {{ $ticket->subject }}</span></flux:table.cell>
                        <flux:table.cell>
                            <p>{{ $ticket->user->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $ticket->user->email }}</p>
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $ticket->last_reply_at?->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$ticket->statusColor()">{{ $ticket->statusLabel() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end"><flux:button size="sm" :href="route('admin.tickets.show', $ticket)" wire:navigate>{{ __('Open') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="5" class="text-center">{{ __('Nothing here.') }}</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
