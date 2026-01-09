<div class="modal-header fin-sub bg-primary text-white">
  <h5 class="modal-title">Statement — {{ $user->name }}</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body fin-sub">
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Date</th>
          <th>Type</th>
          <th>Direction</th>
          <th class="text-end">Amount</th>
          <th>Reference</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
      @forelse($rows as $t)
        <tr>
          <td>{{ optional($t->created_at)->format('Y-m-d H:i') }}</td>
          <td><span class="badge bg-light text-dark">{{ $t->kind }}</span></td>
          <td>
            @if($t->direction === 'income')
              <span class="badge bg-success">Income</span>
            @else
              <span class="badge bg-danger">Expense</span>
            @endif
          </td>
          <td class="text-end">{{ number_format($t->amount, 2) }}</td>
          <td>{{ $t->reference }}</td>
          <td>{{ $t->note }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted">No transactions.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="modal-footer fin-sub">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
