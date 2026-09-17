@extends(auth()->check()?'layouts.customer':'layouts.site')
@section('title','Services')
@section('content')
<div @class(['service-catalog-page','is-guest'=>!auth()->check()])><div class="site-wide py-5">
    <div class="catalog-hero"><div><span class="cyber-kicker">LIVE SERVICE CATALOG</span><h1>Choose a service</h1><p>@auth Your account price is shown for your current group. @else Compare every available group price before you create an account. @endauth</p></div>
        <form class="catalog-search"><select class="form-select" name="type"><option value="">All services</option>@foreach($types as $key=>$model)<option value="{{ $key }}" @selected($selectedType===$key)>{{ ['imei'=>'IMEI','server'=>'Server','file'=>'File','smm'=>'SMM'][$key] }}</option>@endforeach</select><input class="form-control" name="q" value="{{ $q }}" placeholder="Search services…"><button class="btn cyber-primary"><i class="fas fa-search"></i><span>Search</span></button></form>
    </div>
    <div class="catalog-grid">@forelse($services as $service)
        <article class="catalog-card"><div class="catalog-card-top"><span class="service-type">{{ strtoupper($service['type']) }}</span>@if($service['delivery'])<span class="delivery"><i class="far fa-clock"></i> {{ $service['delivery'] }}</span>@endif</div><h2>{{ $service['name'] }}</h2>
            <div class="price-matrix">@forelse($service['prices'] as $price)<div class="price-chip"><span>{{ $price['group'] }}</span><strong>${{ number_format((float)$price['price'],2) }}</strong>@auth<small>Your plan</small>@endauth</div>@empty<div class="price-empty">Price available after contact</div>@endforelse</div>
            @auth<a class="btn cyber-primary w-100" href="{{ route('customer.orders.create',['type'=>$service['type'],'service'=>$service['id']]) }}">Request this service <i class="fas fa-arrow-right"></i></a>@else<a class="btn catalog-login w-100" href="{{ route('login') }}">Log in to order <i class="fas fa-arrow-right"></i></a>@endauth
        </article>
    @empty<div class="catalog-empty"><i class="fas fa-magnifying-glass"></i><h3>No services found</h3><p>Try another service type or search phrase.</p></div>@endforelse</div>
</div></div>
@endsection
