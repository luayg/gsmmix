# SMM pricing and billing

The SMM provider catalog stores the provider `rate` in `smm_services.cost`. For PerfectPanel-style APIs, normal quantity services are priced per 1,000 units, while package services are priced per order. Drip-feed quantity is per run, so total billable units are `quantity * runs`.

## Billing modes

The order pipeline now records a billing snapshot in `smm_orders.params.billing` and applies these safe rules:

- Default and other quantity services: `rate * quantity / 1000`.
- Drip-feed: `rate * quantity * runs / 1000`.
- Package: one full service rate per order; quantity is not multiplied.
- Custom Comments: non-empty comment lines are the billable quantity.
- Mentions Custom List: non-empty username lines are the billable quantity.
- Subscriptions: pre-charge is allowed only when Min equals Max and Posts/Old Posts give a finite count. Variable or unlimited subscriptions are refused instead of guessing a charge.

Group-specific SMM rates are used by the backend, not only displayed in the modal.

## Precision

SMM rates can produce charges smaller than one cent. `users.balance` is therefore widened to four decimal places and refund/recharge arithmetic uses four decimal places. Existing whole-cent balances remain numerically unchanged.

## Historical orders

This change does not rewrite historical SMM orders or customer balances. Existing order finance state remains untouched. New SMM orders store the exact pricing mode, rate snapshot, billable units, total customer charge, provider cost estimate and profit estimate used at creation time.

## Safety

Provider submission still uses the existing SMM gateway. No provider credentials are changed, and tests do not make live provider calls.
