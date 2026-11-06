@props(['label', 'icon' => null, 'hint' => null])

{{-- min-w-0 lets the card shrink inside a grid; the value scales with the card's width (container query) and may wrap, so large amounts never spill out. --}}
<div {{ $attributes->class('@container min-w-0 rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900') }}>
    <div class="flex items-center justify-between gap-2">
        <flux:text class="text-sm font-medium">{{ $label }}</flux:text>
        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-400/15 dark:text-brand-300">
                <flux:icon :name="$icon" variant="mini" />
            </span>
        @endif
    </div>
    <div data-stat-value class="mt-2 font-display text-lg leading-tight font-semibold break-words text-zinc-900 @[14rem]:text-xl @[18rem]:text-2xl dark:text-white [&_.whitespace-nowrap]:whitespace-normal">{{ $slot }}</div>
    @if ($hint)
        <flux:text class="mt-1 text-xs">{{ $hint }}</flux:text>
    @endif
</div>
