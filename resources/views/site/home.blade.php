@extends('layouts.site')
@section('title','Professional GSM Services · '.config('app.name'))
@section('content')
@php
    $serviceCards = [
        ['imei','IMEI Services','Unlocking, carrier checks and device services','mobile-screen-button','cyan'],
        ['server','Server Services','Credits, activations and remote tools','server','blue'],
        ['file','File Services','Firmware, certificates and repair files','file-lines','violet'],
        ['smm','SMM Services','Social media growth and engagement','users','green'],
    ];
@endphp
<div class="public-dark-home">
    <section class="cyber-hero" style="--hero-image:url('{{ asset('images/site/gsm-hero-v2.webp') }}')">
        <div class="site-wide cyber-hero-inner"><div class="cyber-copy">
            <div class="cyber-kicker">{{ $t('home.kicker','GLOBAL MOBILE SOLUTIONS') }}</div>
            <h1>{{ $t('home.title','Powering Mobile Professionals') }}</h1>
            <p><strong>Unlock. Repair. Service.</strong> {{ ($siteSettings['general.site_tagline'] ?? '') ?: $t('home.subtitle','Professional tools, services and support for the mobile industry — faster, smarter, worldwide.') }}</p>
            <div class="d-flex flex-wrap gap-3 mt-4"><a class="btn cyber-primary" href="{{ route('site.services') }}">{{ $t('home.explore','Explore services') }} <i class="fas fa-arrow-right"></i></a><a class="btn cyber-secondary" href="{{ route('site.store') }}">{{ $t('home.view_products','View products') }}</a></div>
            <div class="cyber-trust"><span><i class="fas fa-check"></i> {{ $t('home.trusted','Trusted by professionals') }}</span><span><i class="fas fa-check"></i> {{ $t('home.instant','Instant delivery') }}</span><span><i class="fas fa-check"></i> {{ $t('home.secure','Secure & reliable') }}</span></div>
        </div></div>
    </section>

    @if($banners->isNotEmpty())<section class="dark-section pt-4"><div class="site-wide"><div id="homeBannerCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel"><div class="carousel-inner">@foreach($banners as $banner)<div @class(['carousel-item','active'=>$loop->first])><div class="home-banner-frame"><img src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->title ?: 'Featured banner' }}"><div class="home-banner-overlay"><h2>{{ $banner->title }}</h2><p>{{ $banner->text }}</p>@if($banner->button_label&&$banner->button_url)<a class="btn cyber-primary" href="{{ $banner->button_url }}">{{ $banner->button_label }}</a>@endif</div></div></div>@endforeach</div></div></div></section>@endif

    <section class="dark-section stats-wrap"><div class="site-wide"><div class="cyber-stats">
        <div><i class="fas fa-layer-group"></i><strong>{{ collect($counts)->filter(fn($v)=>$v!==null)->sum() }}+</strong><span>{{ $t('home.active_services','Active services') }}</span></div><div><i class="fas fa-earth-americas"></i><strong>190+</strong><span>{{ $t('home.countries','Countries supported') }}</span></div><div><i class="fas fa-headset"></i><strong>24/7</strong><span>{{ $t('home.support','Expert support') }}</span></div><div><i class="fas fa-bolt"></i><strong>Instant</strong><span>{{ $t('home.delivery','Digital delivery') }}</span></div>
    </div></div></section>

    <section class="dark-section"><div class="site-wide"><div class="cyber-heading"><div><span>{{ $t('home.what_we_do','WHAT WE DO') }}</span><h2>{{ $t('home.our_services','Our Services') }}</h2></div><a href="{{ route('site.services') }}">{{ $t('home.view_all_services','View all services') }} <i class="fas fa-arrow-right"></i></a></div><div class="cyber-service-grid">@foreach($serviceCards as $item)@if($counts[$item[0]]!==null)<a class="cyber-service-card tone-{{ $item[4] }}" href="{{ route('site.services',['type'=>$item[0]]) }}"><i class="fas fa-{{ $item[3] }}"></i><div><h3>{{ $item[1] }}</h3><p>{{ $item[2] }}</p><small>{{ $counts[$item[0]] }} available</small></div><i class="fas fa-chevron-right arrow"></i></a>@endif @endforeach</div></div></section>

    @if($products->isNotEmpty())<section class="dark-section products-section"><div class="site-wide"><div class="cyber-heading"><div><span>STORE</span><h2>{{ $t('home.latest_products','Latest Products') }}</h2></div><a href="{{ route('site.store') }}">{{ $t('home.view_all_products','View all products') }} <i class="fas fa-arrow-right"></i></a></div><div class="cyber-products">@foreach($products as $product)<article class="cyber-product"><div class="product-visual">@if($product->main_image)<img src="{{ asset($product->main_image) }}" alt="{{ $product->name }}">@else<i class="fas fa-box-open"></i>@endif @if($product->new)<span>NEW</span>@endif</div><div class="product-copy"><small>{{ $product->category?->name ?: 'GSM Tool' }}</small><h3>{{ $product->name }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags($product->description),70) }}</p><div><strong>${{ number_format($product->price,2) }}</strong><a href="{{ route('site.store') }}" aria-label="View {{ $product->name }}"><i class="fas fa-cart-shopping"></i></a></div></div></article>@endforeach</div></div></section>@endif

    @if($page?->translations->first()?->content)<section class="dark-section"><div class="site-wide cyber-content">{!! $page->translations->first()->content !!}</div></section>@endif
</div>
@endsection
