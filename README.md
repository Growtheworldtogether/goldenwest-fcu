# Golden West Federal Credit Union

A fictional, member-owned credit union with a full online banking site, built in Laravel as an academic simulation. Every feature works end to end, but **no real money, securities or cryptocurrency is involved**, and only fake data should ever be entered.

The full specification is in [`docs/SPEC.md`](docs/SPEC.md). Read it before changing anything.

## What it is

- **Public website:** home, accounts, loans, rates, digital banking, security, join, help center, contact, and legal pages. Admins edit the text, images, name and logo from a Branding area.
- **Member portal:** accounts, transfers, deposits, statements, bill pay, savings goals, spending insights, loans, virtual card (on request), simulated crypto (per-user flag), security center and support tickets.
- **Admin console:** members, approve, reject or hold transactions with reasons, lock accounts with reasons, adjust balances with descriptions, KYC review, support tickets, audit log, announcements, branding, and log in as a member.
- **Two roles only:** `user` and `admin`. Admin titles (Teller, Manager, Compliance, Support Agent, Super Admin) are permission presets.

## Rules the code must keep

1. Money is stored as integer cents. Never use floats for amounts.
2. Only `TransferService` may change a balance, always inside a database transaction with row locks.
3. Every rejection, hold and account lock needs a reason code and text.
4. Audit rows are append-only and written by the service layer.
5. No view may hard-code the credit union's name. It comes from the brand settings.
6. The simulation notice in the footer is locked and cannot be removed.

## Status

| Part | State |
| --- | --- |
| Specification | Done (`docs/SPEC.md`) |
| `Money`, loan `AmortizationSchedule`, fake `CardNumber` | Done, checked by `php scripts/verify-domain.php` (37 checks) |
| Laravel application (migrations, services, portals) | Not started: needs Composer to install Laravel (see below) |

Build order and the check for each step are in the spec under **Build plan** (M0 to M11).

## Setup (once packages can be installed)

Requires PHP 8.3, Composer, Node 20 or newer, and MySQL 8 or Postgres.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Mail uses SMTP (SMTP2GO or Mailtrap). The scheduler needs this cron line on a real host:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Checks you can run today

```bash
php scripts/verify-domain.php
```

## Known simplifications

- One routing number for the whole credit union.
- ACH and wire settlement are simulated by scheduled jobs.
- Virtual card numbers use a fake range (prefix `999999`) with a valid check digit.
- Crypto is simulated: addresses are set by admins, balances are bookkeeping only.
- No real identity verification.
