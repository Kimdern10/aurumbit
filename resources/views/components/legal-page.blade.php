@props(['title', 'intro' => null])

<x-layouts::site :title="$title">
    <div class="bg-ink-950 py-14 text-white">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold tracking-wide text-brand-300 uppercase">{{ __('Legal') }}</p>
            <h1 class="mt-2 font-display text-3xl font-bold sm:text-4xl">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-3 text-zinc-300">{{ $intro }}</p>
            @endif
            <p class="mt-4 text-xs text-zinc-400">{{ __('Last updated: :date', ['date' => 'October 30, 2026']) }}</p>
        </div>
    </div>

    <article class="mx-auto max-w-3xl px-4 py-12 text-[15px] leading-relaxed sm:px-6 lg:px-8 [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-ink-900 dark:[&_h2]:text-white [&_li]:mt-1.5 [&_p]:mt-4 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-5 [&_a]:font-medium [&_a]:text-brand-700 [&_a]:underline dark:[&_a]:text-brand-300">
        {{ $slot }}
    </article>
</x-layouts::site>
