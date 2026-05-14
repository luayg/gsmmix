# AGENTS.md

## Cursor Cloud specific instructions

### Overview

GSMMix is a Laravel 12 admin panel for managing GSM/phone unlocking services. It uses MySQL, PHP 8.3, and npm-managed frontend assets (Vite + Bootstrap 5 + Tailwind CSS 4).

### Services

| Service | Command | Notes |
|---------|---------|-------|
| Laravel dev server | `php artisan serve --host=127.0.0.1 --port=8000` | Main app |
| MySQL | `sudo service mysql start` | Must be running before Laravel |
| Vite build | `npm run build` | Use build instead of `npm run dev` (see caveat below) |
| Queue worker | `php artisan queue:listen --tries=1` | Only needed for provider sync jobs |

### Running the app

1. Ensure MySQL is running: `sudo service mysql start`
2. Run `php artisan serve --host=127.0.0.1 --port=8000`
3. Access at http://127.0.0.1:8000

### Key gotchas

- **Vite dev server (`npm run dev`) fails** due to summernote's jQuery dependency not resolving in esbuild. Use `npm run build` to compile assets, then run only the Laravel server. The built assets in `public/build/` are served statically.
- **Migrations require MySQL** — the migration `2025_10_09_022540_add_unique_indexes_to_users_columns.php` queries `information_schema.statistics`, which is MySQL-specific. SQLite will not work for the full migration set.
- **PHPUnit tests use SQLite in-memory** (configured in `phpunit.xml`). The feature test for `/` will return 302 (redirect to login) since all routes are auth-protected. This is expected behavior, not a bug.
- **Login** uses a `login` field that accepts either email or username, and checks `status = 'active'`.
- **Default test account**: `test@example.com` / `password` (created by DatabaseSeeder + tinker setup; has Administrator role).

### Commands reference

- **Tests**: `php artisan test`
- **Lint check**: `vendor/bin/pint --test`
- **Lint fix**: `vendor/bin/pint`
- **Build frontend**: `npm run build`
- **Fresh database**: `php artisan migrate:fresh --seed --force`
