@extends('layouts.guest')
@section('title','Verify email')
@section('content')
<div class="auth-page"><div class="auth-card auth-card-compact">
<a class="auth-brand" href="{{ route('home') }}">@include('shared.brand')</a>
<div class="auth-icon"><i class="fas fa-envelope-open-text"></i></div>
<h1>Verify your email</h1>
<p class="text-muted">We sent a 6-digit verification code to <strong>{{ $email }}</strong>. The code expires in 15 minutes.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register.verify.submit') }}">@csrf
<label class="form-label" for="verification_code">Verification code</label>
<input class="form-control form-control-lg auth-code" id="verification_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
<button class="btn btn-primary btn-lg w-100 mt-4">Verify and continue</button>
</form>
<p class="small text-muted mt-4 mb-0">If you did not receive the message, check the spam folder or register again after the code expires.</p>
</div></div>
@endsection
