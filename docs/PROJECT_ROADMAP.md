# Remaining project work

This is the agreed sequence after the access, reference-integrity and service-pricing repairs. Completed pull requests are the source of truth; an empty placeholder or a passing unit test alone does not mean a module is complete.

1. **Service writes.** Shared validation, linked-group protection, custom-field preservation, provider ownership and saved manual/automatic prices are covered by the management changes. Keep all four editor regressions in CI. Browser verification on the deployed local database remains part of acceptance.
2. **Service routes.** Retire the unused actions with no implementations; verify the shipped create, JSON edit, save, bulk activation and delete paths and their permissions.
3. **Provider import and synchronization.** Review repeated imports, provider ownership, group locking, local-field/price preservation and failure handling against current main. Use synthetic provider responses; do not assume every edge case is already broken.
4. **Service orders and balances.** Extend integrated MariaDB tests for create, charge, cancel, refund, dispatch failure and retry races. Product-order concurrency is covered separately and does not prove the service dispatch pipeline is complete.
5. **Store and product orders.** The local product-order lifecycle is implemented in the management changes. Verify catalog/category forms and the local-stock/manual-delivery flows through the browser; continue checking deletion and update races across the catalog.
6. **Users, permissions and interface.** Review form preservation, errors, and button visibility for least-privileged staff. Server authorization must remain authoritative.
7. **Unfinished pages.** Finance summaries and basic reports now use real records. Define and implement invoices, settings, content pages, downloads and system tools separately. Their availability pages deliberately make no claim of functional completion.
8. **Release verification.** Test a clean install and an upgrade on disposable MariaDB data; build assets, run integrity audits, and update the older setup documentation. The npm vulnerability report still needs a dependency-specific audit before changing lockfiles.

After each reviewed change, run the relevant CI gates, merge successful work into main, and provide the local pull and deployment commands. Never present an open draft PR as an installed or merged fix.
