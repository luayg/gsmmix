@extends('layouts.admin')
@section('title', $gateway ? 'Edit payment gateway' : 'Add payment gateway')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">{{ $gateway ? 'Edit '.$gateway->name : 'Add payment gateway' }}</h4><p class="text-muted mb-0">Configure identity, customer instructions, pricing, currencies and secure credentials.</p></div><a class="btn btn-outline-secondary" href="{{ route('admin.settings.payment') }}">Back to gateways</a></div>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" enctype="multipart/form-data" action="{{ $gateway ? route('admin.settings.payment.update',$gateway) : route('admin.settings.payment.store') }}">@csrf @if($gateway)@method('PUT')@endif
<div class="card"><div class="card-header"><i class="fas fa-cog me-1"></i> Gateway settings</div><div class="card-body">@include('admin.settings.payment.partials.form')</div></div><div class="sticky-bottom bg-white border-top py-3 text-end"><button class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> {{ $gateway?'Save changes':'Create gateway' }}</button></div></form>
@endsection
