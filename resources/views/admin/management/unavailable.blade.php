@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container py-5">
  <div class="card"><div class="card-body">
    <h1 class="h4">{{ $title }}</h1>
    <p class="text-muted">{{ $message }}</p>
    <a class="btn btn-outline-primary" href="{{ route('admin.dashboard') }}">Back to dashboard</a>
  </div></div>
</div>
@endsection
