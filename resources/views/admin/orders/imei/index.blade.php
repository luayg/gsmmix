@extends('layouts.admin')

@section('title', 'IMEI Orders')

@section('content')
  @include('admin.orders._index', [
    'pageTitle' => 'IMEI Orders',
  ])
@endsection
