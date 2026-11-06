<?php

use App\Models\SupportTicket;
use App\Notifications\AccountActivity;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Support ticket')] class extends Component {
    use WithFileUploads;

    public SupportTicket $ticket;

    public string $message = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    public function send(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $this->ticket->reply(Auth::user(), $this->message, $this->attachment);
        $this->ticket->user->notify(new AccountActivity(
            'Support replied',
            'We replied to your ticket "'.$this->ticket->subject.'": '.Str::limit($this->message, 300),
            route('support.show', $this->ticket),
        ));

        $this->ticket->unsetRelation('messages');
        $this->reset('message', 'attachment');
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
        <flux:textarea wire:model="message" :label="__('Reply to the user')" rows="4" :description="__('The user gets a notification in their dashboard and by email.')" />
        <flux:input type="file" wire:model="attachment" :label="__('Attach a screenshot or PDF (optional)')" :description="__('JPG, PNG, WEBP or PDF, up to 5 MB.')" accept=".jpg,.jpeg,.png,.webp,.pdf" />
        <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send reply') }}</flux:button>
    </form>
</div>
