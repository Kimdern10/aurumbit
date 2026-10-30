@props(['value'])
{{--
    A QR-style pattern drawn from the address. It is deliberately NOT a scannable
    code, so nobody can send real coins to the demo address by mistake.
--}}
@php
    $size = 25;
    $bits = '';
    foreach (str_split(hash('sha256', $value).hash('sha256', strrev($value)).hash('sha256', $value.'x')) as $hex) {
        $bits .= str_pad(base_convert($hex, 16, 2), 4, '0', STR_PAD_LEFT);
    }
    $isFinder = fn (int $x, int $y): bool => ($x < 7 && $y < 7) || ($x >= $size - 7 && $y < 7) || ($x < 7 && $y >= $size - 7);
@endphp

<div {{ $attributes->class('relative inline-block rounded-xl bg-white p-3') }}>
    <svg viewBox="0 0 {{ $size }} {{ $size }}" class="size-44" shape-rendering="crispEdges" aria-label="{{ __('Demo QR pattern, not scannable') }}" role="img">
        @foreach (range(0, $size - 1) as $y)
            @foreach (range(0, $size - 1) as $x)
                @if (! $isFinder($x, $y) && ($bits[($y * $size + $x) % strlen($bits)] ?? '0') === '1')
                    <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1" fill="#0b1b3a" />
                @endif
            @endforeach
        @endforeach
        @foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$fx, $fy])
            <rect x="{{ $fx + 0.5 }}" y="{{ $fy + 0.5 }}" width="6" height="6" fill="none" stroke="#0b1b3a" stroke-width="1" />
            <rect x="{{ $fx + 2 }}" y="{{ $fy + 2 }}" width="3" height="3" fill="#c9a227" />
        @endforeach
    </svg>
    <span class="absolute inset-0 flex items-center justify-center">
        <span class="rotate-[-12deg] rounded-md bg-gold-400 px-2 py-0.5 text-xs font-bold tracking-wider text-navy-950 shadow">{{ __('DEMO') }}</span>
    </span>
</div>
