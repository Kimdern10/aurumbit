@props(['points' => [], 'height' => 180, 'empty' => 'No data yet'])
{{--
    A small area chart drawn on the server as SVG.
    $points: list of ['label' => string, 'value' => int|float, 'text' => string (tooltip)]
--}}
@php
    $count = count($points);
    $width = 600;
    $padY = 12;
    $values = array_column($points, 'value');
    $max = $count ? max($values) : 0;
    $min = $count ? min(0, min($values)) : 0;
    $range = max(1, $max - $min);

    $coords = [];
    foreach ($points as $i => $point) {
        $x = $count > 1 ? round($i * $width / ($count - 1), 2) : $width / 2;
        $y = round($height - $padY - (($point['value'] - $min) / $range) * ($height - 2 * $padY), 2);
        $coords[] = [$x, $y];
    }

    $line = collect($coords)->map(fn ($c, $i) => ($i ? 'L' : 'M').$c[0].' '.$c[1])->implode(' ');
    $area = $count ? $line.' L'.end($coords)[0].' '.$height.' L'.$coords[0][0].' '.$height.' Z' : '';
    $gradientId = 'area-'.uniqid();
@endphp

<div {{ $attributes->class('w-full') }}>
    @if ($count < 2)
        <div class="flex items-center justify-center rounded-lg border border-dashed border-zinc-200 text-sm text-zinc-400 dark:border-white/10" style="height: {{ $height }}px">{{ $empty }}</div>
    @else
        <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="w-full overflow-visible" style="height: {{ $height }}px" role="img" aria-label="{{ __('Chart') }}">
            <defs>
                <linearGradient id="{{ $gradientId }}" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0" stop-color="#1652f0" stop-opacity="0.35" />
                    <stop offset="1" stop-color="#1652f0" stop-opacity="0" />
                </linearGradient>
            </defs>
            @foreach ([0.25, 0.5, 0.75] as $grid)
                <line x1="0" x2="{{ $width }}" y1="{{ $height * $grid }}" y2="{{ $height * $grid }}" class="stroke-zinc-200 dark:stroke-white/10" stroke-dasharray="4 6" vector-effect="non-scaling-stroke" />
            @endforeach
            <path d="{{ $area }}" fill="url(#{{ $gradientId }})" />
            <path d="{{ $line }}" fill="none" stroke="#1652f0" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
            @foreach ($coords as $i => [$x, $y])
                <circle cx="{{ $x }}" cy="{{ $y }}" r="9" fill="transparent" class="peer"><title>{{ $points[$i]['label'] }}: {{ $points[$i]['text'] ?? $points[$i]['value'] }}</title></circle>
            @endforeach
        </svg>
        <div class="mt-2 flex justify-between text-[11px] text-zinc-400">
            <span>{{ $points[0]['label'] }}</span>
            <span>{{ $points[$count - 1]['label'] }}</span>
        </div>
    @endif
</div>
