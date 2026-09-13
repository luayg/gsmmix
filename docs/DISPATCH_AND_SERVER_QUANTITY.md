# Dispatch claims and Server quantity billing

## Concurrent dispatch protection

Pending-order commands first select candidates and then atomically claim each row under a database row lock. A claim requires `api_order=1`, `status=waiting`, an empty `remote_id`, and `processing=0`. The claim sets `processing=1` and `status=inprogress` before the provider request. A second worker cannot claim the same row.

Normal provider/network outcomes remain owned by `OrderDispatcher`. An unexpected local exception releases an unresolved claim back to `waiting` without printing or storing the exception text in the order payload.

This greatly reduces duplicate provider submissions caused by overlapping cron/worker runs. It cannot make an external provider exactly-once if the provider accepts an order but the network response is lost before a `remote_id` is recorded; provider-side idempotency would be required for that stronger guarantee.

## Server quantity billing

The admin Server-order flow historically sent `quantity` to the provider while debiting only one unit from `users.balance`. Server orders created by that flow are now finalized during the same database transaction:

- Server quantity is billed per unit.
- The first unit debit performed by the existing controller is adjusted to the real total.
- Server group pricing is honored when configured.
- `price`, `order_price`, `profit`, and `request.charged_amount` store totals for the whole quantity.
- Refund/recharge therefore uses the same total amount.
- Insufficient balance throws the existing `INSUFFICIENT_BALANCE` signal, rolling back the whole creation transaction.

The observer applies only to the admin order pipeline marker (`request_uid`). Legacy/direct provider test endpoints are deliberately left unchanged.

The browser summary is also tightened for Server orders so the displayed Price/After balance uses `unit price × quantity`. Run the Vite production build after pulling this change.

## SMM is intentionally separate

SMM catalog values come from the provider `rate` field and service types include Default, Package, Drip-feed, Subscriptions, Custom Comments and other modes. This change does **not** blindly multiply SMM rate by quantity. SMM billing needs an explicit per-service billing mode before changing customer balances.
