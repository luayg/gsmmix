# Group pricing

Each group price has an explicit `auto_price` mode:

- Automatic prices use the current service cost and profit, then apply the saved group discount.
- Manual prices keep the saved amount, including zero and amounts equal to the service price.
- Reset clears the discount and restores automatic mode. That mode survives saving and reopening.
- Existing rows remain manual after migration because their original intent cannot be reconstructed safely. Use Reset on rows that should follow service pricing.

The editor, order previews, initial charges, File/Server billing observers, SMM quotes, and pricing audits use the same mode. The stored `price` of an automatic row is a snapshot, not its current effective price. Readers must calculate it from the service.

## Deploy

Apply the database migration before serving the updated code. The new column defaults to manual mode; the migration does not overwrite saved prices or discounts.

```sh
php artisan migrate --force
npm ci
npm run build
php artisan view:clear
```

The frontend build is required for the Server and SMM order previews. Existing saved orders and historical charges are not changed.

## Verify

```sh
node --test tests/js/*.test.mjs
vendor/bin/phpunit --testdox
```

Regression coverage includes editor save/reopen/Reset, manual prices equal to the default, zero prices, fixed and percentage discounts, all four service API endpoints, bulk import mode, current automatic rates at billing time, and legacy migration behavior.
