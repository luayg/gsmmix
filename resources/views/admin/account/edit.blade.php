@extends('layouts.admin')
@section('title', $t('admin.account.title', 'Administrator account'))
@section('content')
<div class="admin-page-head">
  <div><span class="admin-kicker">{{ $t('admin.account.kicker','ACCOUNT & SECURITY') }}</span><h1>{{ $t('admin.account.title','Administrator account') }}</h1><p>{{ $t('admin.account.subtitle','Manage your administrator identity, password and security status.') }}</p></div>
</div>
@if(session('ok'))<div class="alert alert-success">{{ $t(session('ok'), session('ok')) }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-4">
  <div class="col-xl-7">
    <div class="card admin-panel"><div class="card-header"><i class="fas fa-user-shield me-2"></i>{{ $t('admin.account.identity','Administrator identity') }}</div><div class="card-body p-4">
      <form method="POST" action="{{ route('admin.account.update') }}">@csrf @method('PUT')
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">{{ $t('common.full_name','Full name') }}</label><input class="form-control" name="name" value="{{ old('name',$user->name) }}" required></div>
          <div class="col-md-6"><label class="form-label">{{ $t('common.username','Username') }}</label><input class="form-control" name="username" value="{{ old('username',$user->username) }}" required></div>
          <div class="col-12"><label class="form-label">{{ $t('common.email','Email address') }}</label><input type="email" class="form-control" name="email" value="{{ old('email',$user->email) }}" required></div>
        </div>
        <button class="btn btn-primary mt-4"><i class="fas fa-floppy-disk me-2"></i>{{ $t('common.save_changes','Save changes') }}</button>
      </form>
    </div></div>

    <div class="card admin-panel mt-4" id="password"><div class="card-header"><i class="fas fa-key me-2"></i>{{ $t('admin.password.title','Change password') }}</div><div class="card-body p-4">
      <p class="text-muted">{{ $t('admin.password.help','Use at least 12 characters. Changing the password refreshes your secure session.') }}</p>
      <form method="POST" action="{{ route('admin.account.password') }}">@csrf @method('PUT')
        <div class="row g-3">
          @if(!$user->google_id)<div class="col-12"><label class="form-label">{{ $t('admin.password.current','Current password') }}</label><input type="password" class="form-control" name="current_password" autocomplete="current-password" required></div>@endif
          <div class="col-md-6"><label class="form-label">{{ $t('admin.password.new','New password') }}</label><input type="password" class="form-control" name="password" minlength="12" autocomplete="new-password" required></div>
          <div class="col-md-6"><label class="form-label">{{ $t('admin.password.confirm','Confirm new password') }}</label><input type="password" class="form-control" name="password_confirmation" minlength="12" autocomplete="new-password" required></div>
        </div>
        <button class="btn btn-primary mt-4"><i class="fas fa-shield-halved me-2"></i>{{ $t('admin.password.update','Update password') }}</button>
      </form>
    </div></div>
  </div>
  <div class="col-xl-5">
    <div class="card admin-panel"><div class="card-header"><i class="fas fa-shield me-2"></i>{{ $t('admin.security.title','Security status') }}</div><div class="card-body p-4">
      <div class="account-security-row"><span><i class="fas fa-user-check"></i>{{ $t('admin.security.role','Administrator role') }}</span><b class="text-success">{{ $t('common.active','Active') }}</b></div>
      <div class="account-security-row"><span><i class="fas fa-envelope-circle-check"></i>{{ $t('admin.security.email','Email verification') }}</span><b class="{{ $user->email_verified_at?'text-success':'text-warning' }}">{{ $user->email_verified_at?$t('common.verified','Verified'):$t('common.pending','Pending') }}</b></div>
      <div class="account-security-row"><span><i class="fas fa-mobile-screen-button"></i>{{ $t('admin.security.two_factor','Two-factor authentication') }}</span><b class="{{ $user->two_factor_enabled?'text-success':'text-warning' }}">{{ $user->two_factor_enabled?$t('common.enabled','Enabled'):$t('common.disabled','Disabled') }}</b></div>
      <div class="account-security-row"><span><i class="fas fa-clock"></i>{{ $t('admin.security.last_update','Account updated') }}</span><b>{{ $user->updated_at?->diffForHumans() }}</b></div>
      <a class="btn btn-outline-primary w-100 mt-3" href="{{ route('admin.logs.access') }}"><i class="fas fa-list-check me-2"></i>{{ $t('admin.security.access_logs','Review access logs') }}</a>
    </div></div>
  </div>
</div>
@endsection
