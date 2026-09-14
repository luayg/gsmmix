@php($u = $user)
@php($blocked = (bool)($deletionInspection['blocked'] ?? false))
<form class="modal-content js-ajax-form" action="{{ route('admin.users.destroy',$u) }}" method="POST">
  @csrf @method('DELETE')
  <div class="modal-header text-white" style="background:#dc3545">
    <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete user</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    @if($blocked)
      <div class="alert alert-warning mb-3">
        This user cannot be hard-deleted because historical or financial data must be preserved.
      </div>
      <ul class="mb-0">
        @if(($deletionInspection['orders_total'] ?? 0) > 0)
          <li>Historical orders: {{ (int)$deletionInspection['orders_total'] }}</li>
        @endif
        @if(($deletionInspection['finance_transactions'] ?? 0) > 0)
          <li>Finance transactions: {{ (int)$deletionInspection['finance_transactions'] }}</li>
        @endif
        @if(abs((float)($deletionInspection['balance'] ?? 0)) > 0.000001)
          <li>Balance: {{ number_format((float)$deletionInspection['balance'], 4, '.', '') }}</li>
        @endif
        @if(!empty($deletionInspection['finance_account_nonzero']))
          <li>Finance account contains non-zero totals.</li>
        @endif
      </ul>
      <div class="small text-muted mt-3">Set the account status to inactive instead of deleting it.</div>
    @else
      Are you sure you want to delete <strong>{{ $u->name }}</strong>?
    @endif
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
    @unless($blocked)
      <button class="btn btn-danger" type="submit">Delete</button>
    @endunless
  </div>
</form>
