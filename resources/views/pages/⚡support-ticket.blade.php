<?php

use App\Models\SupportTicket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Support ticket')] class extends Component {
    public SupportTicket $ticket;

    public string $message = '';

    public function mount(SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === Auth::id(), 404);

        $this->ticket = $ticket;
    }

    public function send(): void
    {
        abort_if($this->ticket->status === 'closed', 403);

        $this->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);

        $this->ticket->reply(Auth::user(), $this->message);
        $this->ticket->unsetRelation('messages');
        $this->reset('message');
    }
};
?>

<div class="max-w-3xl space-y-6">
    <div>
        <flux:link :href="route('support')" wire:navigate class="text-sm">← {{ __('All tickets') }}</flux:link>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <flux:heading size="xl">#{{ $ticket->id }} · {{ $ticket->subject }}</flux:heading>
            <flux:badge size="sm" :color="$ticket->statusColor()">{{ $ticket->statusLabel() }}</flux:badge>
        </div>
    </div>

    <x-ticket-thread :ticket="$ticket" />

    @if ($ticket->status === 'closed')
        <flux:callout icon="lock-closed">
            <flux:callout.text>{{ __('This ticket is closed. Open a new ticket if you need more help.') }}</flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="send" class="space-y-3">
            <flux:textarea wire:model="message" :label="__('Your reply')" rows="3" />
            <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send reply') }}</flux:button>
        </form>
    @endif
</div>
