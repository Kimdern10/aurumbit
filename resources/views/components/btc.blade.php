@props(['sats', 'signed' => false])
{{-- A satoshi amount shown in BTC, e.g. <x-btc :sats="1250000" /> → 0.0125 BTC --}}
<span {{ $attributes->class('whitespace-nowrap tabular-nums') }}>{{ $signed && $sats > 0 ? '+' : '' }}{{ \App\Support\Btc::format($sats) }} <span class="text-[0.8em] font-medium opacity-70">BTC</span></span>
