<?php

use App\Models\SupportTicket;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Support')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $subject = '';

    public string $message = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    /**
     * @return LengthAwarePaginator<int, SupportTicket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return Auth::user()->supportTickets()->latest('updated_at')->paginate(10);
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['required', 'string', 'min:4', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $key = 'ticket:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('subject', __('You have opened several tickets recently. Please wait before opening another.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        $ticket = DB::transaction(function (): SupportTicket {
            $ticket = Auth::user()->supportTickets()->create(['subject' => $this->subject]);
            $ticket->reply(Auth::user(), $this->message, $this->attachment);

            return $ticket;
        });

        Flux::toast(variant: 'success', text: __('Ticket sent. We will reply here soon.'));
        $this->redirectRoute('support.show', $ticket, navigate: true);
    }
};
?>

<div class="max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Support') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Ask us anything about your account, deposits, plans or withdrawals.') }}</flux:text>
    </div>

    <flux:card>
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ __('Open a new ticket') }}</flux:heading>
            <flux:input wire:model="subject" :label="__('Subject')" :placeholder="__('e.g. My deposit has not been credited')" />
            <flux:textarea wire:model="message" :label="__('Message')" rows="4" :description="__('Include the transaction ID if your question is about a deposit or withdrawal. Never share your password or wallet recovery words.')" />
            <flux:input type="file" wire:model="attachment" :label="__('Attach a screenshot or PDF (optional)')" :description="__('JPG, PNG, WEBP or PDF, up to 5 MB.')" accept=".jpg,.jpeg,.png,.webp,.pdf" />
            <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send') }}</flux:button>
        </form>
    </flux:card>

    <flux:card class="!p-0 overflow-hidden">
        <div class="px-4 pt-4"><flux:heading>{{ __('Your tickets') }}</flux:heading></div>
        <ul class="mt-2 divide-y divide-zinc-100 dark:divide-white/10">
            @forelse ($this->tickets as $ticket)
                <li wire:key="t-{{ $ticket->id }}">
                    <a href="{{ route('support.show', $ticket) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-white/5">
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-zinc-900 dark:text-white">#{{ $ticket->id }} · {{ $ticket->subject }}</span>
                            <span class="text-xs text-zinc-500">{{ __('Updated :time', ['time' => $ticket->updated_at?->diffForHumans()]) }}</span>
                        </span>
                        <flux:badge size="sm" :color="$ticket->statusColor()">{{ $ticket->statusLabel() }}</flux:badge>
                    </a>
                </li>
            @empty
                <li class="px-4 py-4 text-sm text-zinc-500">{{ __('No tickets yet.') }}</li>
            @endforelse
        </ul>
        <div class="px-4 pb-4">{{ $this->tickets->links() }}</div>
    </flux:card>
</div>
