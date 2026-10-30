@props(['title', 'intro' => null])

<x-layouts::site :title="$title">
    <div class="bg-navy-950 py-14 text-white">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold tracking-wide text-gold-300 uppercase">{{ __('Legal') }}</p>
            <h1 class="mt-2 font-display text-3xl font-bold sm:text-4xl">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-3 text-zinc-300">{{ $intro }}</p>
            @endif
            <p class="mt-4 text-xs text-zinc-400">{{ __('Last updated: :date', ['date' => '30 October 2026']) }}</p>
        </div>
    </div>

    <article class="mx-auto max-w-3xl px-4 py-12 text-[15px] leading-relaxed sm:px-6 lg:px-8 [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-navy-900 dark:[&_h2]:text-white [&_li]:mt-1.5 [&_p]:mt-4 [&_ul]:mt-4 [&_ul]:list-disc [&_ul]:pl-5 [&_a]:font-medium [&_a]:text-gold-700 [&_a]:underline dark:[&_a]:text-gold-300">
        <div class="rounded-xl border border-gold-300/60 bg-gold-50 p-4 text-sm text-gold-900 dark:border-gold-400/30 dark:bg-gold-400/10 dark:text-gold-100">
            <strong>{{ __('Demo platform, no real funds.') }}</strong>
            {{ __('This text is a starting draft for a demonstration website. It must be reviewed by a qualified lawyer and matched to the operator\'s licences before any real launch.') }}
        </div>

        {{ $slot }}
    </article>
</x-layouts::site>
