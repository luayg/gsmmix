@extends('layouts.admin')

@section('title', $title ?? 'Orders')

@section('content')
  @include('admin.orders._index', [
    'title' => $title,
    'kind' => $kind,
    'routePrefix' => $routePrefix,
    'rows' => $rows,
    'providers' => $providers ?? collect([]),
  ])
@endsection
