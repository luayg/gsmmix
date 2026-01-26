@extends('layouts.guest')

@section('title','Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header fw-bold">
                تسجيل الدخول
            </div>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ url('/login') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Email أو Username</label>
                        <input
                            type="text"
                            name="login"
                            value="{{ old('login') }}"
                            class="form-control"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            required
                        >
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="remember" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Login
                    </button>
                </form>

                <hr>

                <div class="text-muted small">
                    بعد تسجيل الدخول سيتم تحويلك تلقائيًا للـ Dashboard أو للصفحة التي كنت تحاول فتحها (مثل API Management).
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
