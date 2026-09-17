@php
    $pageTitle = trim($__env->yieldContent('title'));
    $siteName = $siteSettings['general.site_name'] ?? config('app.name', 'GSM MIX');
    $metaTitle = $siteSettings['general.meta_title'] ?? '';
    $description = $siteSettings['general.meta_description'] ?? ($siteSettings['general.site_tagline'] ?? '');
    $favicon = $siteSettings['general.favicon'] ?? null;
@endphp
<title>{{ $pageTitle !== '' ? $pageTitle.' · '.$siteName : ($metaTitle ?: $siteName) }}</title>
@if($description)<meta name="description" content="{{ $description }}">@endif
@if($siteSettings['general.meta_keywords'] ?? null)<meta name="keywords" content="{{ $siteSettings['general.meta_keywords'] }}">@endif
@if($favicon)<link rel="icon" href="{{ asset('storage/'.$favicon) }}">@endif
