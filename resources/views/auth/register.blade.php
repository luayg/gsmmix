@extends('layouts.guest')
@section('title','Create account')
@section('content')
<div class="auth-page"><div class="auth-card">
<div class="auth-card-side">
<a class="auth-brand" href="{{ route('home') }}">@include('shared.brand')</a>
<div><span class="eyebrow text-info">START IN MINUTES</span><h1>Everything you need for your GSM business.</h1><p>Create your workspace, fund your balance and place orders from one fast dashboard.</p></div>
<div class="auth-trust"><span><i class="fas fa-shield-halved"></i> Secure</span><span><i class="fas fa-bolt"></i> Automated</span><span><i class="fas fa-headset"></i> Supported</span></div>
</div>
<div class="auth-card-form"><div class="auth-form-inner auth-register-inner">
<a class="auth-mobile-brand" href="{{ route('home') }}">@include('shared.brand')</a>
<h2>Create your account</h2><p class="text-muted">Join the professional GSM service platform.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register') }}">@csrf<div class="row g-3">
<div class="col-md-6"><label class="form-label">Full name</label><input class="form-control form-control-lg" name="name" value="{{ old('name') }}" autocomplete="name" required></div>
<div class="col-md-6"><label class="form-label">Username</label><input class="form-control form-control-lg" name="username" value="{{ old('username') }}" autocomplete="username" required></div>
<div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control form-control-lg" name="email" value="{{ old('email') }}" autocomplete="email" required></div>
<div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control form-control-lg" name="password" autocomplete="new-password" required></div>
<div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" class="form-control form-control-lg" name="password_confirmation" autocomplete="new-password" required></div>
</div><button class="btn btn-primary btn-lg w-100 mt-4">Create account</button></form>
<p class="text-center text-muted mt-4">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
</div></div></div></div>
@endsection
