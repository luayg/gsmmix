@extends('layouts.customer')
@section('title','Passkeys')
@section('content')
<div class="row justify-content-center"><div class="col-xl-9">
<div class="panel-card">
<span class="eyebrow">ACCOUNT SECURITY</span>
<div class="d-flex justify-content-between align-items-start gap-3"><div><h1 class="h3 fw-bold">Passkeys</h1><p class="text-muted mb-0">Sign in with Windows Hello, Face ID, Touch ID, or your device screen lock.</p></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#passkeySetup"><i class="fas fa-key me-2"></i>Add passkey</button></div>
<div data-passkey-register-message hidden></div>
@if($passkeys->isEmpty())<div class="border rounded p-4 mt-4 text-center text-muted"><i class="fas fa-key fa-2x mb-3"></i><div>No passkeys have been added yet.</div></div>@else<div class="list-group list-group-flush mt-4">@foreach($passkeys as $passkey)<div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center gap-3"><div><strong>{{ $passkey->name }}</strong><div class="small text-muted">{{ $passkey->authenticator ?: 'Passkey' }} · Added {{ optional($passkey->created_at)->diffForHumans() }}@if($passkey->last_used_at) · Last used {{ $passkey->last_used_at->diffForHumans() }}@endif</div></div><button type="button" class="btn btn-sm btn-outline-danger" data-passkey-delete="{{ route('passkey.destroy',$passkey) }}">Remove</button></div>@endforeach</div>@endif
</div></div></div>
<div class="modal fade" id="passkeySetup" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Add a passkey</h2><button class="btn-close" data-bs-dismiss="modal"></button></div><form data-passkey-register-form><div class="modal-body"><p class="text-muted">Give this passkey a recognizable name, such as “Office PC” or “My phone”.</p><label class="form-label">Passkey name</label><input class="form-control form-control-lg" name="name" maxlength="120" required autocomplete="off"><div class="small text-muted mt-3"><i class="fas fa-shield-halved me-1"></i>Your fingerprint, face, or device PIN never leaves your device.</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Continue on this device</button></div></form></div></div></div>
@endsection
