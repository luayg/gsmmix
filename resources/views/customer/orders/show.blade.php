@extends('layouts.customer') @section('title','Order #'.$order['id']) @section('content')
<div class="page-heading"><div><span class="eyebrow">ORDER DETAILS</span><h1>Order #{{ $order['id'] }}</h1></div></div><div class="gsm-card detail-card">@include('customer.orders._details')</div><a class="btn btn-outline-primary mt-3" href="{{ route('customer.orders.type',$order['type']) }}">Back to orders</a>
@endsection
