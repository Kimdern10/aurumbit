@props([
    'title',
    'description',
    'eyebrow' => null,
])

<div class="flex w-full flex-col">
    @if ($eyebrow)
        <p class="mb-3 text-xs font-semibold tracking-[0.14em] text-brand-600 uppercase dark:text-brand-400">{{ $eyebrow }}</p>
    @endif
    <h1 class="font-display text-3xl font-extrabold text-ink-950 dark:text-white">{{ $title }}</h1>
    <p class="mt-2 text-[0.95rem] leading-relaxed text-ink-500 dark:text-ink-300">{{ $description }}</p>
</div>
