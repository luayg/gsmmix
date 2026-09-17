@php
    $brandName = $siteSettings['general.site_name'] ?? config('app.name', 'GSM MIX');
    $brandLogo = $siteSettings['general.logo'] ?? null;
@endphp
@if($brandLogo)
    <img class="site-brand-logo" src="{{ asset('storage/'.$brandLogo) }}" alt="{{ $brandName }}">
@else
    <span>{{ $brandName }}</span>
@endif
