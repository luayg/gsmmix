# Provider status and finance invariants

A provider result and the customer's financial state must not drift apart.

## Invariants

- `rejected` / `cancelled` orders are refunded exactly once.
- `success` orders are charged. If a provider reverses an earlier rejection after the refund has already been spent, the late confirmed success is re-charged and may make the account balance negative. This is recorded in `request.recharged_with_negative_balance` so the delivered service is not silently left unpaid.
- `waiting` / `inprogress` never force a debit by the observer. Official admin reactivation still uses the existing guarded recharge flow and refuses reactivation when the available balance is insufficient.
- The observer is a last-line invariant. Existing explicit refund/recharge calls remain valid because the finance service is idempotent and row-locked.

## Read-only local audit

Run:

```powershell
php artisan orders:status-audit
```

The command prints counts only and does not contact providers or change data. Investigate any non-zero anomaly before bulk repair, especially:

- `success_refunded`
- `failed_charged`
- `final_processing_true`
- `waiting_with_remote_id`
- `inprogress_without_remote_id`

This audit complements `orders:finance-audit`.

## Remaining provider-sync hardening

Provider API transport/protocol errors must never be interpreted as a business rejection. The legacy IMEI/Server/File/SMM sync commands still need a dedicated refactor so transient status-check failures preserve the last known business status, and redundant pre-dispatch paths in Server/File/SMM sync commands can be removed in favor of the row-locked dispatch commands.
