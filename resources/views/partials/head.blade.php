@php
    $siteName = \App\Models\Setting::get('site_name');
    $pageTitle = filled($title ?? null) ? $title.' - '.$siteName : $siteName.' - '.\App\Models\Setting::get('tagline');
    $pageDescription = $description ?? \App\Models\Setting::get('seo_description');
@endphp
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta name="twitter:card" content="summary">
<meta name="theme-color" content="#0b1b3a">
@if (! request()->routeIs('home', 'legal.*'))
    <meta name="robots" content="noindex">
@endif

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
