# Order finance integrity

## Canonical spendable balance

`users.balance` is the live spendable balance. Order creation already locks the user row
and debits this value. Refund/recharge operations change the same value. The finance
summary must therefore never reconstruct `users.balance` from historical credit totals.
`finance_accounts.total_receipts` and `paid_credits` remain accounting totals; they are
not a replacement for the live balance. `locked_amount` is legacy/informational until
order reservation is migrated to one dedicated ledger service.

Manual paid credits, payments and overdraft changes now adjust the current live balance
by their delta under a row lock. Unpaid credit does not increase spendable balance.
Removing paid credit that has already been spent, or reducing overdraft below an already
used amount, is rejected instead of silently writing off the difference.

## Refund/recharge state

Order request metadata uses `financial_state=charged|refunded`. Legacy orders without
that field remain compatible: `refunded_at` without `recharged_at` is treated as refunded.
Refund and recharge lock the order row first and re-read state inside the transaction,
then lock the user row. Stale workers therefore cannot apply the same refund/recharge
twice. Re-activating a refunded order (`waiting`, `inprogress`, or `success`) re-charges
it before the status change; insufficient balance leaves the old status intact.

This patch does not call suppliers and does not automatically repair historical rows.

## Read-only local audit

After pulling this change, run:

```powershell
php artisan optimize:clear
php artisan orders:finance-audit
```

The command prints counts only. It does not print order IDs, customer data, supplier
payloads, API keys, or modify the database. Investigate non-zero values for:

- `active_or_success_refunded`: an active/success order still financially refunded.
- `rejected_or_cancelled_not_refunded`: charged terminal order not marked refunded.
- `missing_charge_metadata`: priced user order that lacks `request.charged_amount`.

Historical repair must be reviewed separately because manual balance adjustments may
have happened after the affected order. Do not bulk-adjust balances from the audit alone.

## Known follow-up work

This change intentionally does not decide SMM quantity billing. Supplier SMM prices can
be per-1000, package, subscription, drip-feed or other semantics, so generic
`price * quantity` would be unsafe. Server quantity billing and dispatch-worker claiming
also need dedicated tests before changing production behavior. External provider calls
should eventually be moved outside the user-balance database transaction.
