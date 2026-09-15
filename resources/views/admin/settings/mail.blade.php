@extends('layouts.admin')

@section('title', 'Mail settings')

@section('content')
<div class="card mb-4">
  <div class="card-header bg-primary text-white"><i class="fas fa-envelope me-1"></i> Mail settings</div>
  <div class="card-body">
    @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('admin.settings.mail.update') }}">
      @csrf @method('PUT')
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Mailer</label><select name="mailer" class="form-select" id="mailer">@foreach(['smtp'=>'SMTP','sendmail'=>'Sendmail','log'=>'Log (testing)'] as $value=>$label)<option value="{{ $value }}" @selected(old('mailer', $settings['mail.mailer']) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">SMTP host</label><input name="host" class="form-control" maxlength="255" value="{{ old('host', $settings['mail.host']) }}"></div>
        <div class="col-md-3"><label class="form-label">Port</label><input type="number" min="1" max="65535" name="port" class="form-control" value="{{ old('port', $settings['mail.port']) }}"></div>
        <div class="col-md-4"><label class="form-label">Encryption</label><select name="encryption" class="form-select"><option value="">None</option><option value="tls" @selected(old('encryption', $settings['mail.encryption']) === 'tls')>TLS</option><option value="ssl" @selected(old('encryption', $settings['mail.encryption']) === 'ssl')>SSL</option></select></div>
        <div class="col-md-4"><label class="form-label">Username</label><input name="username" autocomplete="off" class="form-control" value="{{ old('username', $settings['mail.username']) }}"></div>
        <div class="col-md-4"><label class="form-label">Password</label><input type="password" name="password" autocomplete="new-password" class="form-control" value="" placeholder="{{ $passwordConfigured ? 'Leave blank to keep current password' : 'Enter SMTP password' }}"><div class="form-text">{{ $passwordConfigured ? 'A password is securely stored.' : 'No saved password.' }}</div></div>
        <div class="col-md-5"><label class="form-label">From address</label><input type="email" name="from_address" required class="form-control" value="{{ old('from_address', $settings['mail.from_address']) }}"></div>
        <div class="col-md-5"><label class="form-label">From name</label><input name="from_name" required maxlength="120" class="form-control" value="{{ old('from_name', $settings['mail.from_name']) }}"></div>
        <div class="col-md-2"><label class="form-label">Timeout</label><input type="number" name="timeout" min="1" max="60" required class="form-control" value="{{ old('timeout', $settings['mail.timeout']) }}"></div>
      </div>
      <div class="mt-4 text-end"><button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Save mail settings</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="fas fa-paper-plane me-1"></i> Send test email</div>
  <div class="card-body">
    <p class="text-muted small">Save the settings first. Test sending is limited to three attempts per minute.</p>
    <form method="POST" action="{{ route('admin.settings.mail.test') }}" class="row g-2 align-items-end">
      @csrf
      <div class="col-md-8"><label class="form-label">Recipient</label><input type="email" name="test_email" required class="form-control" value="{{ old('test_email', auth()->user()->email) }}"></div>
      <div class="col-md-4"><button class="btn btn-outline-primary w-100" type="submit">Send test email</button></div>
    </form>
  </div>
</div>
@endsection
