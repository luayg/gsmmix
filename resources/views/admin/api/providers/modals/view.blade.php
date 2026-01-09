<div class="modal-header">
  <h5 class="modal-title">API — {{ $provider->name }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  @isset($info['error'])
    <div class="alert alert-danger">{{ $info['error'] }}</div>
  @endisset

  <dl class="row mb-0">
    <dt class="col-sm-3">Type</dt>
    <dd class="col-sm-9">{{ strtoupper($provider->type) }}</dd>

    <dt class="col-sm-3">URL</dt>
    <dd class="col-sm-9"><a href="{{ $provider->url }}" target="_blank">{{ $provider->url }}</a></dd>

    <dt class="col-sm-3">Username</dt>
    <dd class="col-sm-9">{{ $provider->username ?: '—' }}</dd>

    <dt class="col-sm-3">Active</dt>
    <dd class="col-sm-9">
      <span class="badge {{ $provider->active ? 'bg-success' : 'bg-secondary' }}">
        {{ $provider->active ? 'Active' : 'Inactive' }}
      </span>
    </dd>

    <dt class="col-sm-3">Balance</dt>
    <dd class="col-sm-9 text-{{ ($provider->balance ?? 0) > 0 ? '' : 'danger' }}">
      ${{ number_format((float)($provider->balance ?? 0), 2) }}
    </dd>
  </dl>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
