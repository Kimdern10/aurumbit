@props(['ticket', 'viewerIsStaff' => false])
{{-- The messages of a support ticket, as a chat. --}}
<div {{ $attributes->class('space-y-3') }}>
    @foreach ($ticket->messages as $message)
        @php($mine = $viewerIsStaff ? $message->from_staff : ! $message->from_staff)
        <div wire:key="m-{{ $message->id }}" @class(['flex', 'justify-end' => $mine])>
            <div @class([
                'max-w-[85%] rounded-2xl px-4 py-3 text-sm',
                'rounded-br-sm bg-ink-900 text-white dark:bg-brand-400/15 dark:text-brand-50' => $mine,
                'rounded-bl-sm border border-zinc-200 bg-white text-zinc-800 dark:border-white/10 dark:bg-ink-900 dark:text-zinc-100' => ! $mine,
            ])>
                <p class="mb-1 text-xs font-semibold opacity-70">
                    {{ $message->from_staff ? __(':site support', ['site' => \App\Models\Setting::get('site_name')]) : $ticket->user->name }}
                    · {{ $message->created_at?->format('M j, g:i A') }}
                </p>
                <p class="whitespace-pre-line">{{ $message->body }}</p>
                @if ($url = $message->attachmentUrl(forAdmin: $viewerIsStaff))
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-lg bg-black/5 px-2.5 py-1.5 text-xs font-medium underline dark:bg-white/10" data-ticket-attachment>
                        <flux:icon.paper-clip variant="micro" class="shrink-0" />
                        <span class="truncate">{{ $message->attachment_name }}</span>
                    </a>
                @endif
            </div>
        </div>
    @endforeach
</div>
