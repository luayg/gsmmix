@extends('layouts.admin')
@section('title', 'Create product order')
@section('content')
<div class="container py-4">
  <h1 class="h4 mb-3">Create product order</h1>
  <div class="card"><div class="card-body">@include('admin.orders.product._form')</div></div>
</div>
@endsection
