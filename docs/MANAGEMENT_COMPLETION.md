# Reviewed management changes

## Services

Service-group edits refuse a kind change while any IMEI, Server, File or SMM service is linked. Name and ordering edits within the same kind remain supported. Move the services to an appropriate group before retyping an occupied group.

Create and update use the same custom-field input validation. Malformed JSON/list entries fail before writes. An explicit empty custom_fields list takes precedence over alternate custom_fields_json input. Omission preserves fields on update and means no custom fields on creation.

Nine unused service routes with missing actions were retired: the four modal/edit routes, four toggle routes and Server sync-fields. The shipped editor continues to use show.json + update, and activation uses bulk. No live provider sync is added.

## Product orders

Product orders now use products and product_orders, with their own create/list/detail/update pages.

- Each order represents one item. The catalog Price in Credits is authoritative; converted_price/currency and client-submitted amounts do not set the debit.
- A unique form submission ID prevents retrying one form from purchasing again.
- Product and customer must be active. Device-based products require an identifier.
- Manual-source products are charged once and enter Waiting; an operator can set In progress, deliver a result, or reject/cancel.
- A selected Local Source requires an unused, unexpired matching reply. Source and reply locks serialize stock allocation across customers/products. No reply is recycled, even when a product is marked Unlimited.
- Delivery snapshots the result in the order and records both reply/order backlinks.
- Cancellation/rejection of an undelivered new order refunds its original charge once. Reactivation recharges that same amount and fails if credits are insufficient.
- Successful delivered orders retain their result, charge and stock assignment. Corrections requiring a refund need a separate reviewed financial adjustment.
- Historical orders without the new billing metadata allow notes but refuse status transitions. The migration never infers old charges or changes balances.
- Product billing metadata is stored with the order, consistent with the existing service-order pipeline. The account-transactions page is the existing account-adjustment register, not a complete ledger of all order debits.

## Pages

Finance overview, account statements, account transactions, and user/service/product reports now read actual application data.

Invoices, content pages, downloads, settings, system actions and log viewers still require their own functional specifications and implementation. Their routes now return an explicit HTTP 501 availability page (or structured JSON) instead of a plain-text success response. This change does **not** claim those modules are implemented.

## Installation

After updating the checkout, clear cached views/configuration and apply the additive product-order migration:

~~~bat
git pull --ff-only origin main && php artisan optimize:clear && php artisan migrate --force
~~~

No frontend bundle or dependency change is included. The migration adds nullable submission, device, billing/result and reply-time fields. Existing orders remain intact; rollback refuses to discard financial/delivery history.

Useful existing read-only checks:

~~~bat
php artisan products:integrity-audit --details=0
php artisan replies:integrity-audit --details=0
~~~

## Validation

The initial test-only commit reproduced three failures on the previous main: linked group retyping, malformed create JSON accepted, and an empty list overridden by alternate field JSON.

The suite exercises the actual authenticated HTTP routes, old-data migration preservation, price authority, exact four-decimal balances, retry/cancel/reactivation, stock expiry/device matching, permissions and escaped delivery results. The dedicated disposable MariaDB workflow also launches simultaneous workers for duplicate submissions and competition for one remaining reply. There are no live provider calls or production credentials in these tests.
