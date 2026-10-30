{{-- Shown on every page while the platform runs in demo mode. --}}
<div {{ $attributes->class('flex items-center justify-center gap-2 bg-gold-400 px-4 py-1.5 text-center text-xs font-semibold text-navy-950 sm:text-sm') }} role="note">
    <flux:icon.beaker variant="micro" class="shrink-0" />
    <span>{{ __('Demo platform, no real funds. Balances, deposits and withdrawals are simulated.') }}</span>
</div>
