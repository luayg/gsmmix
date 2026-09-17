<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" dir="{{ app()->getLocale()==='ar'?'rtl':'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">@include('shared.site-meta')@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="public-site-body">
@php($routeFor=['home'=>'home','services'=>'site.services','store'=>'site.store','downloads'=>'site.downloads','place-order'=>'customer.orders.create','orders'=>'customer.orders','account'=>'customer.dashboard'])
<nav class="navbar navbar-expand-lg navbar-dark site-nav fixed-top"><div class="site-wide w-100 d-flex flex-nowrap align-items-center">
<a class="brand-mark" href="{{ route('home') }}">@include('shared.brand')</a><button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="siteNav"><ul class="navbar-nav mx-auto gap-lg-2"><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('home')]) href="{{ route('home') }}">Home</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.services')]) href="{{ route('site.services') }}">Services</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.store')]) href="{{ route('site.store') }}">Products</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.downloads')]) href="{{ route('site.downloads') }}">Downloads</a></li><li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.resellers')]) href="{{ route('site.resellers') }}">Resellers</a></li>@foreach($headerPages->whereNotIn('slug',['home','services','store','downloads','place-order','orders','account','pricing','support']) as $navPage)<li class="nav-item"><a @class(['nav-link','active'=>request()->routeIs('site.page')&&request()->route('page')?->is($navPage)]) href="{{ route('site.page',$navPage) }}">{{ $navPage->translations->first()?->title ?? ucfirst($navPage->slug) }}</a></li>@endforeach</ul>
<div class="site-auth-actions">
@auth
<a class="btn cyber-primary" href="{{ route('customer.dashboard') }}">Dashboard</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn cyber-secondary">Logout</button></form>
@else
@if($siteSettings['general.registration_enabled']??false)<a class="btn cyber-secondary" href="{{ route('register') }}">Register</a>@endif
<a class="btn cyber-primary" href="{{ route('login') }}">Log in</a>
@endauth
</div></div></div></nav>
<main>@yield('content')</main>
<footer class="site-footer py-5"><div class="container"><div class="row g-4"><div class="col-lg-5"><a class="brand-mark" href="{{ route('home') }}">@include('shared.brand')</a><p class="mt-3">{{ $siteSettings['general.site_tagline'] ?? '' }}</p>
@if($siteSettings['general.contact_email']??null)<div><a href="mailto:{{ $siteSettings['general.contact_email'] }}">{{ $siteSettings['general.contact_email'] }}</a></div>@endif
@if($siteSettings['general.contact_phone']??null)<div><a href="tel:{{ $siteSettings['general.contact_phone'] }}">{{ $siteSettings['general.contact_phone'] }}</a></div>@endif
@if($siteSettings['general.contact_address']??null)<div class="mt-2">{{ $siteSettings['general.contact_address'] }}</div>@endif</div>
<div class="col-lg-7"><div class="d-flex flex-wrap gap-4 justify-content-lg-end">@foreach($footerPages as $navPage)<a href="{{ route('site.page',$navPage) }}">{{ $navPage->translations->first()?->title ?? ucfirst($navPage->slug) }}</a>@endforeach</div></div></div><hr class="border-secondary"><div class="small">© {{ date('Y') }} {{ $siteSettings['general.site_name'] ?? config('app.name','GSMMIX') }}. All rights reserved.</div></div></footer>
@stack('scripts')</body></html>
