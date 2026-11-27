# cPanel deployment runbook

This release is built for PHP 8.2 and MySQL/MariaDB. The preferred cPanel layout keeps the Laravel application outside the public web root and points the domain document root at the application's `public` directory.

## Release contents

- Application source and production frontend build.
- Composer `vendor` directory for hosts without shell Composer access.
- Existing `public/uploads` media.
- Sanitized `.env.cpanel.example`; no local `.env` or application key is included.
- Gzipped MySQL dump under `database/exports/`.
- Additive seller-module migrations and rollback-safe documentation.

## Installation

1. Back up the current cPanel files and database.
2. Upload and extract the archive into a versioned directory such as `/home/ACCOUNT/apps/elite-marketplace/releases/20261127`.
3. In cPanel Domains, set the domain document root to `/home/ACCOUNT/apps/elite-marketplace/current/public` after creating `current` as the active release directory or symlink.
4. Copy `.env.cpanel.example` to `.env` and replace every placeholder. Set `APP_URL` to the HTTPS production URL and keep `APP_DEBUG=false`.
5. Create the MySQL database and user in cPanel, grant all privileges, and import `database/exports/elite_marketplace_20261127.sql.gz` through phpMyAdmin or the cPanel terminal.
6. If this dump is imported into an empty database, do not run `migrate:fresh`. Run `php artisan migrate --force` only to apply migrations newer than the bundled dump.
7. Ensure `storage` and `bootstrap/cache` are writable by the PHP user, normally `775` on shared hosting.
8. Run the production cache commands:

   ```bash
   php artisan storage:link
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

9. Configure the cPanel cron entry to run every minute:

   ```text
   * * * * * /usr/local/bin/php /home/ACCOUNT/apps/elite-marketplace/current/artisan schedule:run >/dev/null 2>&1
   ```

10. Configure a queue worker only if `QUEUE_CONNECTION` is changed from `sync`. On hosts without Supervisor, use a cPanel cron that runs `queue:work --stop-when-empty` at an appropriate interval.

## Web-server requirements

- PHP 8.2, 64-bit.
- Extensions: ctype, curl, DOM/XML, fileinfo, filter, GD, hash, iconv, JSON, OpenSSL, session, SimpleXML, tokenizer, XMLReader, XMLWriter, ZIP, and Zlib.
- Apache `mod_rewrite` and `.htaccess` overrides enabled.
- HTTPS certificate installed before enabling live payment callbacks.
- Recommended PHP limits: `memory_limit=512M`, `upload_max_filesize=32M`, `post_max_size=40M`, and `max_execution_time=120`.

## Smoke checks

After deployment verify:

- `/` loads the storefront over HTTPS.
- `/login` displays the Elite Group login screen.
- `/admin` redirects unauthenticated users to login and opens after an authorized admin login.
- `/seller` opens for an approved seller.
- A seller can select variant options, generate SKU combinations, save a draft, and submit a private product for approval.
- Product images load from `/uploads` and newly uploaded files are writable.
- Mail delivery, payment callback URLs, and scheduled tasks use the production domain.

## Rollback

Keep the previous release directory and its database backup. To roll back, restore the previous database backup, point the domain/current release back to the previous directory, then clear Laravel caches. Do not attempt a code-only rollback after new database writes unless the accompanying migration rollback has been reviewed.
