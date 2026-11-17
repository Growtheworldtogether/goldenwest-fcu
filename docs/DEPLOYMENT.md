# Putting it on a server

Any host that runs PHP 8.3 or newer with a database works: a VPS, a managed Laravel host, or shared hosting that lets you point the web root at `public/`. This is the same list in any of them.

## Before you start

- PHP 8.3+ with `bcmath`, `ctype`, `curl`, `fileinfo`, `gd` (with WebP support), `mbstring`, `openssl`, `pdo` and the driver for your database, `tokenizer`, `xml`, `zip`.
- MySQL 8 or Postgres for anything more than a demo. SQLite is fine for one person looking at it.
- A domain with HTTPS. The security headers and the secure cookie both assume it.
- Composer on the server, or build the `vendor/` folder elsewhere and upload it. Node is only needed to build the CSS and scripts.

## Steps

1. Put the code on the server and point the web root at the `public/` folder.
2. `cp .env.example .env`, then set:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
   - the `DB_*` values
   - `SESSION_SECURE_COOKIE=true`
   - the `MAIL_*` values for a real sender (SMTP2GO, Mailtrap, SES). Without them, email is only written to the log.
   - optionally `COINGECKO_API_KEY` for a higher price-refresh limit
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan key:generate`
5. `npm ci && npm run build` (or upload the built `public/build` folder).
6. `php artisan migrate --force`
7. Load the reference data the site needs. The seeder that creates demo members refuses to run in production, so run just these:
   ```sh
   php artisan db:seed --class=ProductSeeder --force
   php artisan db:seed --class=ReasonCodeSeeder --force
   php artisan admin:sync
   ```
8. Create the first staff account: `php artisan admin:create you@example.com --name="Your Name"`. It asks for a password of at least 12 characters. Sign in at `/login`, then turn on two-step verification from the Security page.
9. `php artisan storage:link`, then make `storage/` and `bootstrap/cache/` writable by the web user.
10. `php artisan optimize` (caches config, routes and views). Run it again after every deploy.
11. Add the scheduler cron line, or nothing scheduled will run (loan autopay, bill payments, crypto prices):
    ```
    * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
    ```
12. If the site sits behind a load balancer or proxy, trust it so HTTPS and client addresses are read correctly: add `$middleware->trustProxies(at: '*');` (or the proxy's address) inside `withMiddleware` in `bootstrap/app.php`.

## For a demo with members and history

On a throwaway server or staging copy, run `APP_ENV=local php artisan migrate:fresh --seed` once, then switch back. Never do this on a server with real sign-ups: `migrate:fresh` deletes everything.

## After each deploy

```sh
php artisan migrate --force
php artisan optimize
php artisan ledger:verify      # every balance should match the ledger
```

## Checks on the live site

Run the walkthrough in [`ACCEPTANCE.md`](ACCEPTANCE.md). In short: the home page loads over HTTPS, a member can sign in and move money between their accounts, a wire waits for staff, staff can approve it, the audit log shows each step, and `php artisan ledger:verify` ends with "Every account matches the ledger."

## Backups

Back up the database and `storage/app` (uploaded identity documents and check images). Without them, uploaded files and the ledger are gone.
