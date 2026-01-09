@extends('layouts.admin')
@section('title','Login')
@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="mb-3"><i class="fas fa-sign-in-alt me-2"></i> Login (placeholder)</h5>
          <p class="text-muted mb-4">هذه صفحة مؤقتة لتفادي أي 404 عندما نفعّل auth لاحقًا.</p>
          <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
