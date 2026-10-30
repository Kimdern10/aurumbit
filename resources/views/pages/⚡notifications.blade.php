<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Notifications')] class extends Component {
    use WithPagination;

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return Auth::user()->notifications()->paginate(15);
    }

    public function open(string $id): void
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        unset($this->notifications);
    }
};
?>

<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Updates about your deposits, withdrawals, plans and identity checks.') }}</flux:text>
        </div>
        @if (auth()->user()->unreadNotifications()->exists())
            <flux:button wire:click="markAllRead" icon="check">{{ __('Mark all as read') }}</flux:button>
        @endif
    </div>

    <div class="space-y-2">
        @forelse ($this->notifications as $notification)
            @php($tone = $notification->data['tone'] ?? 'info')
            <button type="button" wire:key="n-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')"
                @class([
                    'flex w-full items-start gap-3 rounded-xl border p-4 text-start transition hover:border-brand-400',
                    'border-brand-300 bg-brand-50/60 dark:border-brand-400/40 dark:bg-brand-400/5' => $notification->read_at === null,
                    'border-zinc-200 bg-white dark:border-white/10 dark:bg-ink-900' => $notification->read_at !== null,
                ])>
                <flux:icon :name="match ($tone) { 'success' => 'check-circle', 'danger' => 'x-circle', default => 'information-circle' }"
                    @class(['mt-0.5 size-5 shrink-0', 'text-green-600' => $tone === 'success', 'text-red-600' => $tone === 'danger', 'text-sky-600' => $tone === 'info']) />
                <span class="min-w-0 flex-1">
                    <span class="flex items-center justify-between gap-3">
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $notification->data['title'] ?? '' }}</span>
                        <span class="shrink-0 text-xs text-zinc-500">{{ $notification->created_at?->diffForHumans() }}</span>
                    </span>
                    <span class="mt-0.5 block text-sm text-zinc-600 dark:text-zinc-300">{{ $notification->data['body'] ?? '' }}</span>
                </span>
            </button>
        @empty
            <flux:card class="text-center">
                <flux:icon.bell class="mx-auto size-8 text-zinc-300" />
                <flux:text class="mt-2">{{ __('No notifications yet.') }}</flux:text>
            </flux:card>
        @endforelse
    </div>

    {{ $this->notifications->links() }}
</div>
