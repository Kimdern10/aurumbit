@props(['url'])
@php($siteName = \App\Models\Setting::get('site_name'))
<tr>
<td class="header">
<a href="{{ $url }}" class="brand" style="display: inline-block;">
<img src="{{ asset('icon-192.png') }}" class="logo" width="44" height="44" alt="{{ $siteName }}">
<span class="brand-name">{{ $siteName }}</span>
</a>
</td>
</tr>
