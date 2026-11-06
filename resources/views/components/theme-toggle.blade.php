{{-- Switches between light and dark mode. Flux remembers the choice in the browser. --}}
<flux:button
    x-data
    x-on:click="$flux.dark = ! $flux.dark"
    variant="subtle"
    square
    :aria-label="__('Switch between light and dark mode')"
    data-theme-toggle
    {{ $attributes }}
>
    <flux:icon.moon variant="mini" class="dark:hidden" />
    <flux:icon.sun variant="mini" class="hidden dark:block" />
</flux:button>
