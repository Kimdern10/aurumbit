<?php

use App\Models\SupportTicket;
use App\Notifications\AccountActivity;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Support ticket')] class extends Component {
    public SupportTicket $ticket;

    public string $message = '';

    public function send(): void
    {
        $this->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);

        $this->ticket->reply(Auth::user(), $this->message);
        $this->ticket->user->notify(new AccountActivity(
            'Support replied',
            'We replied to your ticket "'.$this->ticket->subject.'".',
            route('support.show', $this->ticket),
        ));

        $this->ticket->unsetRelation('messages');
        $this->reset('message');
        Flux::toast(variant: 'success', text: __('Reply sent.'));
    }

    public function toggleClosed(): void
    {
        $this->ticket->forceFill(['status' => $this->ticket->status === 'closed' ? 'answered' : 'closed'])->save();
    }
};
?>

<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('admin.tickets')" wire:navigate class="text-sm">← {{ __('All tickets') }}</flux:link>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <flux:heading size="xl">#{{ $ticket->id }} · {{ $ticket->subject }}</flux:heading>
                <flux:badge size="sm" :color="$ticket->statusColor()">{{ $ticket->statusLabel() }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-sm">
                <a href="{{ route('admin.users.show', $ticket->user_id) }}" wire:navigate class="underline">{{ $ticket->user->name }}</a> · {{ $ticket->user->email }}
            </flux:text>
        </div>
        <flux:button wire:click="toggleClosed" :icon="$ticket->status === 'closed' ? 'lock-open' : 'lock-closed'">
            {{ $ticket->status === 'closed' ? __('Reopen') : __('Close ticket') }}
        </flux:button>
    </div>

    <x-ticket-thread :ticket="$ticket" viewer-is-staff />

    <form wire:submit="send" class="space-y-3">
        <flux:textarea wire:model="message" :label="__('Reply to the user')" rows="4" :description="__('The user gets a notification in their dashboard.')" />
        <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send reply') }}</flux:button>
    </form>
</div>
