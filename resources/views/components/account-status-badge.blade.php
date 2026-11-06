@props(['user'])
{{-- "Banned" or "Suspended" (with the end date) badge; shows nothing for active accounts. --}}
@if ($user->isBanned())
    <flux:badge size="sm" color="red" {{ $attributes }}>{{ __('Banned') }}</flux:badge>
@elseif ($user->isSuspended())
    <flux:badge size="sm" color="amber" {{ $attributes }}>
        {{ $user->suspended_until ? __('Suspended until :date', ['date' => $user->suspended_until->format('M j')]) : __('Suspended') }}
    </flux:badge>
@endif
