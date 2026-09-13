# Access-control deployment

This change is repository code, not a deployment to the hosting server. Test a restored
copy of the production database in staging before deploying. Keep the SQL backup
outside the repository. Never run `migrate:fresh` or `db:wipe` against existing data.

## Before pulling

Preserve the server's current `.env` in a private location outside the Git working tree.
An older checkout may still track it, and pulling its deletion can remove that local
file. Restore the server `.env` after pulling if necessary. Do not copy credentials into
Git, issue comments, screenshots, or CI. Keep the existing `APP_KEY` available: the
previous provider-key migration and encrypted values depend on it.

Do not casually run `key:generate` on an existing installation. The CI command with
that name creates only a disposable test key, never a production key.

## Deploy existing installation

After preserving `.env` and stopping traffic/workers as required by your host:

```sh
git pull --ff-only origin main
composer install --no-interaction --prefer-dist
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=RbacSeeder --force
```

An existing active user with the `Administrator` role keeps administrative access.
If no account has that role, select the correct **existing active user ID** and run:

```sh
php artisan admin:grant USER_ID
```

`USER_ID` is a placeholder, not a username. Verify it in your own database first.
The command has a production confirmation; `--force` is available for intentional,
non-interactive deployment. It never creates an account or changes its password,
never selects the first user automatically, and preserves other assigned roles.

Finish deployment according to your hosting setup, including restarting long-running
PHP/queue workers. On production HTTPS, use `APP_DEBUG=false`,
`SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax`.
Do not enable secure-only cookies on a plain-HTTP local development site.

## Access rules

- Guests: login redirect for HTML; HTTP 401 for JSON.
- Inactive users: denied and their session invalidated on the next admin request.
- Basic customers: no admin access.
- `Administrator`: full access to reviewed admin routes.
- `Manager`: baseline access plus create/edit (not delete) for ordinary business modules.
- `Support`: baseline read-only access to users, services, orders and store.
- Existing custom grants are preserved; the seeder adds defaults without resetting them.
- All non-administrators need `admin.access` **and** each endpoint's module permission.
- User-account mutations, role/permission administration, settings and system operations
  additionally require the `Administrator` role. A delegated `users.edit`/`roles.edit`
  permission cannot promote someone or reset an administrator's password.
- Bulk service deletion requires `services.delete`, not merely `services.edit`.
- Unmapped admin routes are denied until reviewed and added to `AdminPermissions`.

Permissions are enforced server-side. Some existing UI buttons may still be visible
and return 403 for staff; sidebar/button visibility can be refined separately.

## Login and sessions

Login accepts email or username and the existing `remember=on` checkbox. Limits are
5 attempts per normalized login/IP pair per 60 seconds, plus 25 attempts per IP per
60 seconds across accounts. The next attempt returns 429 with `Retry-After`.
Production must use a shared cache store when running multiple application servers.

The session ID rotates at login; logout invalidates the session and rotates CSRF.
Password changes invalidate existing authenticated sessions. Protected responses are
not cacheable. Authentication/authorization runs after session initialization and
before admin route-model binding; it is not global pre-session middleware.

## Tests and their limits

```sh
vendor/bin/phpunit --testdox
```

`AdminAccessTest` uses a synthetic SQLite auth/RBAC fixture and temporary file sessions,
never the real SQL backup. It tests fresh-session login, inactive accounts, password
changes, revoked roles, logout replay, throttling, privilege escalation, bulk deletion,
explicit administrator assignment, repeatable seeding, and mapping of registered routes.
`AdminPermissionsTest` tests the pure route-to-permission mapping.

These are authentication/authorization tests, not a full production-MySQL migration,
payment, supplier API, UI, or order-processing certification. No real provider is contacted.

## Outstanding secret incident

Removing `.env` and a dump from the current branch does **not** remove earlier Git history
or revoke copied secrets. Rotate exposed supplier credentials at the suppliers. Plan
application-key rotation together with re-encryption of stored values and session
invalidation; blindly replacing `APP_KEY` makes existing ciphertext unreadable. Cleaning
Git history is a separate coordinated operation, not performed by this change.
