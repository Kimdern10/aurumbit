{{-- A unique gradient id per logo, so a copy hidden with display:none can't break the visible one. --}}
@php($gradientId = 'hashvale-logo-'.\Illuminate\Support\Str::random(6))
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" aria-hidden="true" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#2F63F4" />
            <stop offset="1" stop-color="#0F42C7" />
        </linearGradient>
    </defs>
    <rect width="40" height="40" rx="11" fill="url(#{{ $gradientId }})" />
    <path d="M12.5 10.5h4.2v7.3h6.6v-7.3h4.2v19h-4.2v-7.7h-6.6v7.7h-4.2Z" fill="#FFFFFF" />
    <circle cx="31" cy="10" r="3" fill="#7DD3FC" />
</svg>
