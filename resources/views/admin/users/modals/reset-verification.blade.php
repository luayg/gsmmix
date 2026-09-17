<form class="modal-content js-ajax-form" action="{{ route('admin.users.reset_verification', $user) }}" method="POST">
  @csrf
  <div class="modal-header">
    <h5 class="modal-title">Reset verification methods</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="alert alert-warning mb-3">
      This removes every verification method configured by <strong>{{ $user->name }}</strong>.
    </div>
    <ul class="mb-3">
      <li>Email two-step verification will be disabled for this account.</li>
      <li>The authenticator app secret will be removed.</li>
      <li>{{ $passkeyCount }} registered {{ \Illuminate\Support\Str::plural('passkey', $passkeyCount) }} will be deleted.</li>
    </ul>
    <p class="text-muted mb-0">The user can configure verification methods again from Profile settings. A system-wide administrator requirement, if enabled, still applies.</p>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-danger"><i class="fas fa-shield-halved me-1"></i> Reset all verification</button>
  </div>
</form>
