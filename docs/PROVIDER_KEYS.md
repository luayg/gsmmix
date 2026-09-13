# Provider API key storage

## Scope

This change concerns `api_providers.api_key` only. It does not contact suppliers,
change the server/local `.env`, rotate `APP_KEY`, import the production SQL dump,
or delete customer/order/balance records. Secrets embedded in simple-link URLs,
`params`, historic logs, or earlier Git commits need a separate remediation pass.
Do not treat successful encryption as revocation of an already exposed supplier key.

## What changed

- A dedicated write controller and FormRequest now own the existing provider create
  and update routes. The large legacy controller remains responsible for reads and
  import/sync actions; its old write methods are no longer routed.
- Leaving `api_key` absent, empty, null or whitespace on update preserves the exact
  stored ciphertext server-side. JavaScript is not required to save a replacement.
- Create/edit forms always start with an empty password field. Saved or flashed keys
  are never echoed into them. Explicit original replacement keys are encrypted.
- Omitted provider `params` and flags are preserved on update, preventing unrelated
  edits from erasing authentication settings. Explicit supplied settings can replace them.
- Switching to `simple_link` still clears the separate, unused `api_key` field.
- The model's strict cast uses Laravel encryption without serialization, compatible
  with existing `encrypted` casts. Null/blank legacy fields read as absent; plaintext,
  unreadable and nested ciphertext are not silently sent as credentials.
- A read-only command reports counts, never names, URLs, keys, or raw error bindings.

## Migration safety

The original `2026_09_13_000001` migration has been hardened for installations that
have not run it yet. A **new** `2026_09_14_000001` migration covers installations
where the old one is already recorded. Do not delete migration history or rerun old
migrations manually.

Both paths preflight every row, widen VARCHAR to TEXT where needed, normalize empty
values to NULL, and encrypt legacy plaintext exactly once. Valid readable ciphertext
is preserved byte-for-byte. Recognizable malformed/truncated Laravel payloads,
ciphertext encrypted with an unavailable key, and recognizable nested ciphertext
stop the upgrade rather than being encrypted again. Completely destroyed data cannot
always be distinguished from arbitrary plaintext; use a verified backup to investigate.

The data-conversion pass is transactional and rechecks locked rows. MySQL schema
changes are **not** rolled back with data changes. Stop application/queue writers
while deploying. Rollback is deliberately refused; it cannot restore secrets to plaintext.

## Local update (PowerShell)

Keep a private backup of the local `.env` and database before pulling. Preserve the
existing APP_KEY. Do not share it or run `key:generate` on the existing installation.
Stop application/queue workers during the update. Check `git status`; stop
and resolve local changes without `reset --hard` if the working tree is not clean.

```powershell
git pull --ff-only origin main
php artisan optimize:clear
php artisan providers:keys-audit
```

`legacy_plaintext > 0` before migration means legacy keys need upgrading. An audit
exit code of 1 is expected in that case. If `unreadable` or `nested` is nonzero, stop:
recover the correct application key or original supplier credential before proceeding.
The audit never changes data and cannot recover a key already lost to an earlier edit.

```powershell
php artisan migrate
php artisan providers:keys-audit
```

After a successful migration expect `legacy_plaintext=0`, `unreadable=0`, `nested=0`.
`empty` counts providers with no separate API key; this can be normal for simple links.
For machine-readable counts use `php artisan providers:keys-audit --json`.
No NPM installation/build is required for this patch; the CSS/JS bundles are unchanged.
Restart application/queue workers after completing the upgrade.

## Tests

The normal PHPUnit suite uses synthetic SQLite data and real HTTP create/update routes.
A separate CI job runs the two key migrations against a disposable MariaDB database
with the provider table's column shape (including VARCHAR(255) before migration).
Only invented credentials and `.invalid` URLs are used in CI. No actual SQL dump,
production environment, supplier credentials or network calls belong in these tests.
Tests do not certify every historical migration or supplier API integration.

## Rotation is separate

Configured APP_PREVIOUS_KEYS can let Laravel read earlier ciphertext, but this patch
neither changes keys nor re-encrypts valid old ciphertext. Retain the matching key
until an explicitly planned rotation/re-encryption is complete. An exposed old key
should not remain trusted indefinitely; revocation/history cleanup require coordinated
work. Never enable plaintext fallback to hide a wrong-key error.
