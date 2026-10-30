@props(['label', 'icon' => null, 'hint' => null])

<div {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900') }}>
    <div class="flex items-center justify-between gap-2">
        <flux:text class="text-sm font-medium">{{ $label }}</flux:text>
        @if ($icon)
            <span class="flex size-8 items-center justify-center rounded-lg bg-gold-100 text-gold-700 dark:bg-gold-400/15 dark:text-gold-300">
                <flux:icon :name="$icon" variant="mini" />
            </span>
        @endif
    </div>
    <div class="mt-2 font-display text-xl font-semibold text-zinc-900 sm:text-2xl dark:text-white">{{ $slot }}</div>
    @if ($hint)
        <flux:text class="mt-1 text-xs">{{ $hint }}</flux:text>
    @endif
</div>
