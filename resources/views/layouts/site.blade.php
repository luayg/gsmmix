<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" dir="{{ $currentLanguage?->direction ?? 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">@include('shared.site-meta')@vite(['resources/css/app.css','resources/js/app.js'])
@php($theme=$activePageTheme?->settings ?? []) @php($pageStyle=isset($page)&&$page instanceof \App\Models\Page ? ($page->style??[]) : [])
<style>:root{--page-primary:{{ $theme['primary']??'#1677ff' }};--page-accent:{{ $pageStyle['accent']??$theme['accent']??'#20d9ff' }};--page-background:{{ $pageStyle['background']??$theme['background']??'#061321' }};--page-surface:{{ $theme['surface']??'#0b2033' }};--page-text:{{ $pageStyle['text']??$theme['text']??'#eef8ff' }};--page-muted:{{ $theme['muted']??'#9bb4c8' }};--page-radius:{{ (int)($theme['radius']??16) }}px;--page-width:{{ (int)($pageStyle['content_width']??1200) }}px;--page-heading-scale:{{ (float)($pageStyle['heading_scale']??$theme['heading_scale']??1) }};--page-font-size:{{ (int)($pageStyle['font_size']??$theme['base_font_size']??16) }}px}body.public-site-body{background:var(--page-background);color:var(--page-text);font-family:'{{ $theme['body_font']??'Inter' }}',Arial,sans-serif;font-size:var(--page-font-size)}body.public-site-body h1,body.public-site-body h2,body.public-site-body h3{font-family:'{{ $theme['heading_font']??'Inter' }}',Arial,sans-serif}.site-nav{background:var(--page-surface)!important}.cyber-primary{background:var(--page-primary)!important}.gsm-card{background:var(--page-surface)!important;border-color:var(--page-accent)!important;border-radius:var(--page-radius)!important}.section-title{font-size:calc(2.5rem * var(--page-heading-scale))}.site-wide,.container{max-width:var(--page-width)}.site-footer{background:var(--page-surface)!important;color:var(--page-muted)}</style></head>
<body class="public-site-body">
@php($routeFor=['home'=>'home','services'=>'site.services','store'=>'site.store','downloads'=>'site.downloads','place-order'=>'customer.orders.create','orders'=>'customer.orders','account'=>'customer.dashboard'])
<nav class="navbar navbar-expand-lg navbar-dark site-nav fixed-top"><div class="site-wide w-100 d-flex flex-nowrap align-items-center">
<a class="brand-mark" href="{{ route('home') }}">@include('shared.brand')</a><button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="siteNav"><ul class="navbar-nav mx-auto gap-lg-2">
@if($mainMenu)
@foreach($mainMenu->items as $menuItem)
@php($navPage=$menuItem->page)
@if(!$navPage || ($navPage->status==='published' && (!$navPage->authenticated_only || auth()->check())))
@php($menuRoute=$navPage ? ($routeFor[$navPage->slug]??null) : null)
@php($menuHref=$navPage ? ($menuRoute&&Route::has($menuRoute)?route($menuRoute):route('site.page',$navPage)) : $menuItem->url)
<li class="nav-item"><a class="nav-link" href="{{ $menuHref }}" @if($menuItem->open_new_window) target="_blank" rel="noopener" @endif>{{ $menuItem->label }}</a></li>
@endif
@endforeach
@else<li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('home')]) href="{{ route('home') }}">{{ $t('nav.home','Home') }}</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.services')]) href="{{ route('site.services') }}">{{ $t('nav.services','Services') }}</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.store')]) href="{{ route('site.store') }}">{{ $t('nav.products','Products') }}</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.downloads')]) href="{{ route('site.downloads') }}">{{ $t('nav.downloads','Downloads') }}</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.resellers')]) href="{{ route('site.resellers') }}">{{ $t('nav.resellers','Resellers') }}</a></li>@foreach($headerPages->whereNotIn('slug',['home','services','store','downloads','place-order','orders','account','pricing','support']) as $navPage)<li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.page')&&request()->route('page')?->is($navPage)]) href="{{ route('site.page',$navPage) }}">{{ $navPage->translations->first()?->title ?? ucfirst($navPage->slug) }}</a></li>@endforeach
@endif
</ul>
<div class="dropdown site-language"><button class="btn cyber-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span>{{ $currentLanguage?->flag }}</span> {{ strtoupper($currentLanguage?->code ?? 'EN') }}</button><ul class="dropdown-menu dropdown-menu-end">@foreach($activeLanguages as $language)<li><form method="POST" action="{{ route('locale.update',$language->code) }}">@csrf<button class="dropdown-item {{ app()->getLocale()===$language->locale?'active':'' }}" type="submit"><span class="me-2">{{ $language->flag }}</span>{{ $language->native_name }}</button></form></li>@endforeach</ul></div>
<div class="site-auth-actions">
@auth
<a class="btn cyber-primary" href="{{ route('customer.dashboard') }}">{{ $t('nav.dashboard','Dashboard') }}</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn cyber-secondary">{{ $t('nav.logout','Logout') }}</button></form>
@else
@if($siteSettings['general.registration_enabled']??false)<a class="btn cyber-secondary" href="{{ route('register') }}">{{ $t('nav.register','Register') }}</a>@endif
<a class="btn cyber-primary" href="{{ route('login') }}">{{ $t('nav.login','Log in') }}</a>
@endauth
</div></div></div></nav>
<main>@yield('content')</main>
<footer class="site-footer py-5"><div class="container"><div class="row g-4"><div class="col-lg-5"><a class="brand-mark" href="{{ route('home') }}">@include('shared.brand')</a><p class="mt-3">{{ $siteSettings['general.site_tagline'] ?? '' }}</p>
@if($siteSettings['general.contact_email']??null)<div><a href="mailto:{{ $siteSettings['general.contact_email'] }}">{{ $siteSettings['general.contact_email'] }}</a></div>@endif
@if($siteSettings['general.contact_phone']??null)<div><a href="tel:{{ $siteSettings['general.contact_phone'] }}">{{ $siteSettings['general.contact_phone'] }}</a></div>@endif
@if($siteSettings['general.contact_address']??null)<div class="mt-2">{{ $siteSettings['general.contact_address'] }}</div>@endif</div>
<div class="col-lg-7"><div class="d-flex flex-wrap gap-4 justify-content-lg-end">@foreach($footerPages as $navPage)<a href="{{ route('site.page',$navPage) }}">{{ $navPage->translations->first()?->title ?? ucfirst($navPage->slug) }}</a>@endforeach</div></div></div><hr class="border-secondary"><div class="small">© {{ date('Y') }} {{ $siteSettings['general.site_name'] ?? config('app.name','GSMMIX') }}. {{ $t('common.rights','All rights reserved.') }}</div></div></footer>
@stack('scripts')</body></html>
