@extends('layouts.admin')

@section('title', 'Product Orders')

@section('content')
  @include('admin.orders._index', [
    'pageTitle' => 'Product Orders',
  ])
@endsection
