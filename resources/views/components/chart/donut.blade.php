@props(['segments' => [], 'center' => null, 'caption' => null])
{{--
    A donut chart. $segments: list of ['label' => string, 'value' => int, 'color' => '#hex', 'text' => string]
--}}
@php
    $total = array_sum(array_column($segments, 'value'));
    $radius = 15.915;
    $offset = 0;
@endphp

<div {{ $attributes->class('flex flex-col items-center gap-4 sm:flex-row') }}>
    <div class="relative size-36 shrink-0">
        <svg viewBox="0 0 42 42" class="size-full -rotate-90" role="img" aria-label="{{ $caption ?? __('Allocation') }}">
            <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke-width="5" class="stroke-zinc-100 dark:stroke-white/10" />
            @if ($total > 0)
                @foreach ($segments as $segment)
                    @php($share = $segment['value'] / $total * 100)
                    @if ($share > 0)
                        <circle cx="21" cy="21" r="{{ $radius }}" fill="none" stroke="{{ $segment['color'] }}" stroke-width="5"
                            stroke-dasharray="{{ round($share, 3) }} {{ round(100 - $share, 3) }}" stroke-dashoffset="{{ round(-$offset, 3) }}" />
                        @php($offset += $share)
                    @endif
                @endforeach
            @endif
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            {{ $center }}
        </div>
    </div>
    <ul class="w-full space-y-2 text-sm">
        @foreach ($segments as $segment)
            <li class="flex items-center justify-between gap-3">
                <span class="flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $segment['color'] }}"></span>{{ $segment['label'] }}</span>
                <span class="font-medium text-zinc-900 dark:text-white">{{ $segment['text'] ?? $segment['value'] }}</span>
            </li>
        @endforeach
    </ul>
</div>
