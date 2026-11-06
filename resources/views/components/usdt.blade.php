@props(['micros', 'signed' => false, 'decimals' => 2])
{{-- A micro-USDT amount shown in USDT, e.g. <x-usdt :micros="1250000000" /> → 1,250.00 USDT --}}
<span {{ $attributes->class('whitespace-nowrap tabular-nums') }}>{{ $signed && $micros > 0 ? '+' : '' }}{{ \App\Support\Usdt::format($micros, $decimals) }} <span class="text-[0.8em] font-medium opacity-70">USDT</span></span>
