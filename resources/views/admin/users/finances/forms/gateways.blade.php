<div class="modal-header bg-secondary">
  <h5 class="modal-title">Manage payment gateways — {{ $user->name ?? 'User '.$user->id }}</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" id="gw1" checked>
    <label class="form-check-label" for="gw1">USDT AUTO PAY</label>
  </div>
  <div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" id="gw2">
    <label class="form-check-label" for="gw2">Zain Cash Jordan</label>
  </div>
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" id="gw3" checked>
    <label class="form-check-label" for="gw3">Binance Pay + USDT manual</label>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Save</button>
</div>
