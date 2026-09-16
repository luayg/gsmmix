{{-- [انسخ] --}}
@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
<div class="card">
  <div class="card-body">
    <h5 class="card-title mb-3"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h5>
    <p class="mb-0">مرحباً بك في لوحة التحكم.</p>
    @if($manualPaymentsReview > 0)
      <div class="alert alert-warning d-flex justify-content-between align-items-center mt-4 mb-0">
        <span><strong>{{ $manualPaymentsReview }}</strong> manual payment {{ $manualPaymentsReview === 1 ? 'is' : 'are' }} waiting for review.</span>
        <a class="btn btn-warning" href="{{ route('admin.finances.payment-reviews.index') }}">Review payments</a>
      </div>
    @endif
  </div>
</div>
@endsection
