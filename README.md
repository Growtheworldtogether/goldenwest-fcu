# Golden West Federal Credit Union

A member-owned credit union with a full online banking site, built in Laravel for a course assignment. Every feature works end to end, but **no real money, securities or cryptocurrency is involved, and only made-up data should ever be entered.** The small legal line at the bottom of every page says so, and the line cannot be removed from the Branding screen.

The specification is [`docs/SPEC.md`](docs/SPEC.md). A step-by-step walkthrough for checking the whole site is [`docs/ACCEPTANCE.md`](docs/ACCEPTANCE.md). Putting it on a server is covered in [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## What is in it

- **Public website.** Home, accounts, loans, rates, digital banking, security, business, join, help center, contact and legal pages. Staff edit the text, images, name, logo and colors from the Branding and Site content screens, with drafts, preview, publish and rollback.
- **Member portal.** Accounts and statements, activity with filters and CSV export, transfers between own accounts, to other members and to other banks, check and ACH deposits, bill pay, savings goals, spending insights and budgets, loans, virtual and physical cards, beneficiaries, joint accounts, a security center, support tickets, and email notices.
- **Optional services, switched on per member by staff:** wire transfers (domestic and international), loans and crypto wallets.
- **Staff console.** Member lookup and a Member 360 view; a review queue with reasons and second approvals; locks; balance adjustments; opening history for a new account; identity review; loan, card, joint-account and crypto approvals; tickets and inquiries; the audit log; announcements; settings; branding; and "log in as member" with a time limit.
- **Two roles.** `user` and `admin`. Staff titles (Teller, Support Agent, Compliance, Manager, Super Admin) are permission presets stored in `config/admin.php`.

## Rules the code keeps

1. Money is integer cents, never floats.
2. Only `TransferService` changes a cash balance (and only `CryptoService` changes a crypto balance), inside a database transaction with row locks. `php artisan ledger:verify` recomputes every balance from the ledger.
3. Every rejection, hold and lock carries a reason code and a reason the member can read.
4. The audit log is append-only and written by the service layer.
5. No view hard-codes the credit union's name; it comes from the brand settings.
6. Every success and error message appears as a toast.
7. Members never see staff notes. `Transaction::memberMemo()` shows only a memo the member typed.

## Run it on your computer

Needs PHP 8.3 or newer, Composer, and Node 20 or newer. SQLite is enough; MySQL 8 or Postgres also work.

```sh
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite        # PowerShell: New-Item database\database.sqlite -ItemType File
php artisan migrate --seed            # prints the staff password
npm install && npm run build
php artisan serve
./vendor/bin/pest                     # the whole test suite
php artisan ledger:verify
```

The seeder creates five staff accounts (`admin@`, `teller@`, `manager@`, `compliance@`, `support@example.com`, all with the password it prints, or `SEED_ADMIN_PASSWORD`) and ten members (`<first>.<last>@example.com`, password `GoldenWest2026!` or `SEED_MEMBER_PASSWORD`). Each member has checking and savings with about 75 days of history posted through the ledger. A few have something to look at: a loan on autopay, a loan application waiting for review, a joint account, beneficiaries, a funded crypto wallet with a deposit to confirm, a support ticket, and a wire held for review. The seeder refuses to run in production.

After pulling new code, run `php artisan migrate`.

### Scheduled jobs

On a real host add one cron line. It runs ticket auto-close, loan autopay and late fees, bill payments, automatic savings goals, the monthly spending summary and live crypto prices.

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Locally, `php artisan schedule:work` does the same. Useful by hand: `php artisan crypto:rates`, `php artisan loans:process`, `php artisan members:notify-terms 2027-01-15`, `php artisan admin:sync` (re-applies staff permission presets), `php artisan admin:create <email>` (first staff account on a server).

## Security measures

- Passwords hashed; sign-in limited to five tries a minute per email and address; sign-up, password reset and resend-verification forms limited by address; every signed-in area has a per-user request limit and money-moving forms have tighter ones.
- Optional two-step verification (authenticator app) for members and staff; an email when a new device signs in.
- Staff sessions time out after ten idle minutes; "log in as member" expires after thirty and cannot change credentials.
- Response headers: a content security policy that allows only this site, `X-Frame-Options: DENY`, `nosniff`, a strict referrer policy, a permissions policy, HSTS over HTTPS, and `no-store` on signed-in pages.
- Uploaded identity documents and check images are re-encoded, stored privately under random names and served only to the owner and authorised staff. Account numbers are encrypted and shown as the last four digits.
- Access control is tested route by route: a signed-out visitor, a member, each staff title and one member against another member (`tests/Feature/Security`).

## Known simplifications

These are deliberate. This is a learning project, not a bank.

- One routing number (121478906) for the whole credit union.
- ACH and wire settlement are simulated by scheduled jobs and staff approval. Nothing leaves the system.
- Virtual card numbers use a fake range (prefix `999999`) with a valid check digit.
- **Crypto is a simulation.** Wallet addresses are assigned by staff, deposits and sends are confirmed by staff, and transaction hashes are made up. No real wallet, key or blockchain is involved. Dollar values use live prices from CoinGecko, which is the only outside service the app calls.
- No real identity verification. Staff look at uploaded images and approve or reject them.
- Email goes to the log by default. Set the `MAIL_*` values in `.env` to send real mail.
- The content security policy allows inline scripts and styles, because the pages use small inline scripts. Moving them to files with nonces would tighten it.
- Opening history on a new account is generated, not real activity. It is marked for staff in each transaction and recorded in the audit log.
