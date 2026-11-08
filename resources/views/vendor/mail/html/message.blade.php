@php
    $siteName = \App\Models\Setting::get('site_name');
    $supportEmail = \App\Models\Setting::get('support_email');
    $companyDetails = \App\Models\Setting::get('company_details');
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ __('Questions? Reply to this email or write to :email.', ['email' => $supportEmail]) }}

{{ __('We will never ask for your password, two-step codes or wallet recovery words.') }}

© {{ date('Y') }} {{ $siteName }}.@if (filled($companyDetails)) {{ $companyDetails }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
