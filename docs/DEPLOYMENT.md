# Putting it on a server

Any host that runs PHP 8.3 or newer with a database works: a VPS, a managed Laravel host, or shared hosting that lets you point the web root at `public/`. This is the same list in any of them.

## Before you start

- PHP 8.3+ with `ctype`, `curl`, `fileinfo`, `gd` (with WebP support), `mbstring`, `openssl`, `pdo` and the driver for your database, `tokenizer`, `xml`, `zip`.
- MySQL 8 or Postgres for anything more than a demo. SQLite is fine for one person looking at it.
- A domain with HTTPS. The security headers and the secure cookie both assume it.
- Composer on the server, or build the `vendor/` folder elsewhere and upload it. Node is only needed to build the CSS and scripts.

## Steps

1. Put the code on the server and point the web root at the `public/` folder.
2. `composer install --no-dev --optimize-autoloader`, then `npm ci && npm run build` (or upload the built `public/build` folder). Make `storage/`, `bootstrap/cache/` and the app folder (for the `.env` file) writable by the web user.
3. Open the site in a browser. It goes straight to the **setup wizard** (`/setup`). It needs no `.env` file, no database and no command line:
   - **Owner key**: copy the 8-character code from `storage/app/install-key.txt` (skipped only when you open the page from the server itself; 5 wrong tries lock it for 15 minutes).
   - **Server check**: PHP version, extensions, writable folders, https.
   - **Your credit union**: name, web address, support contacts, routing number, colours, time zone. These become the Branding settings; logos are uploaded later in Admin, then Branding.
   - **Database**: a SQLite file, or MySQL/MariaDB. The connection is tested.
   - **Email**: SMTP2GO (or any SMTP) details, tested; or "later" to log mail until you add them.
   - **First administrator**: a Super Admin with a 12+ character password.
   - **Install**: writes `.env`, creates the tables, loads the reference data (products, reason codes, loan products, crypto assets), saves the branding, creates the administrator, and locks the wizard. After that `/setup` returns 404.
4. Sign in at `/login`, turn on two-step verification from the Security page, then review Admin, then Branding.
5. `php artisan storage:link`, then `php artisan optimize` (run `optimize` again after every deploy).
6. Add the scheduler cron line, or nothing scheduled will run (loan autopay, bill payments, crypto prices):
   ```
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```
7. If the site sits behind a load balancer or proxy, trust it so HTTPS and client addresses are read correctly: add `$middleware->trustProxies(at: '*');` (or the proxy's address) inside `withMiddleware` in `bootstrap/app.php`.

Prefer the command line? Copy `.env.example` to `.env`, set the values, run `php artisan key:generate`, `php artisan migrate --force`, `php artisan db:seed --force` (reference data only), then `php artisan admin:create you@example.com --name="Your Name"`. Create the file `storage/app/installed.lock` to switch the wizard off.

## Starting over

`php artisan platform:reset` deletes everything (members, staff, ledger, branding, uploaded files) and brings the wizard back. Local or staging only: it cannot be undone.

## Demo data

A fresh install has no members and no history. To fill a throwaway copy with a believable demo credit union, run `php artisan db:seed --class=DemoDataSeeder` (refuses in production). Never on a site with real sign-ups.

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
