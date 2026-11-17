# Golden West Federal Credit Union: Specification and Build Plan

Source of truth for the build. Copied from the project document on 2026-11-16. Where this file and a prompt disagree, this file wins.

## 1. Overview and key decisions

Golden West Federal Credit Union is a fictional, member-owned credit union serving members across the United States. It is built in Laravel as a simulation in which every feature works end to end. Members bank online. Admins review money movement, manage members and can sign in as a member to help them.

**Decisions already made**

- **Two roles only:** `user` (a member) and `admin`. Teller, Manager, Compliance, Support Agent and Super Admin are admin **titles**, which are permission presets, not separate roles.
- **Support is a ticket system.** Members open tickets, any admin with ticket permission answers, and decisions can be contested from a ticket.
- **Reasons are mandatory.** Every rejection, hold and account lock carries a reason code plus text, and the member sees it.
- **Balances change only through the ledger.** An admin credit or debit is an adjustment transaction with a description. Money is stored as integer cents.
- **Credit union wording:** members, member numbers, Share Savings, Share Draft Checking, dividends, one routing number for the whole credit union.
- **Optional features sit behind per-user flags** that an admin switches on. The virtual card appears only after a member requests it and an admin approves it.
- **Admin can log in as a member** (impersonation), with strict safeguards (section 2).
- **Joining and opening accounts is free.** There is no opening deposit.
- **No branch locator.** The product has no branch locator, map or location pages. Marketing copy presents Golden West as an established multi-state credit union with a branch network, and never says it has no branches.
- **Simulation notice:** the footer and Terms state that this is an academic project and that no real money, securities or cryptocurrency is involved.

**Scope at a glance**

| Tier | Features |
| --- | --- |
| Core | Public website, registration and 2FA, accounts, ledger, transfers, deposits (cash, check, ACH), decisions with reasons, account locks, admin console, audit log, support tickets, admin balance adjustments, admin login as member |
| Flagged or on request | Virtual card (on request), crypto fund and send (per-user flag), loans, wires and higher limits (per-user flags) |
| Member extras | Bill pay, overdraft protection, savings goals, spending insights, security center, announcements, beneficiaries |
| Last, if time allows | Joint-account screens (the data model supports them from day one) |

## 2. Roles, admin titles and permissions

`users.role` is `user` or `admin`. An admin has a **title** in `admin_profiles` (`user_id`, `title`), and the title maps to a permission preset kept in one config file and stored with `spatie/laravel-permission`. A Super Admin can adjust presets.

| Permission | Teller | Manager | Compliance | Support Agent | Super Admin |
| --- | --- | --- | --- | --- | --- |
| Look up members | all | all | all | all | all |
| Post cash and check deposits | yes | yes | no | no | yes |
| Approve, reject or hold transactions | small amounts | yes | yes | no | yes |
| Lock and unlock accounts | no | yes | yes | no | yes |
| Adjust balances (credit or debit) | no | up to a limit | no | no | yes |
| Review KYC | no | no | yes | no | yes |
| View audit log | no | yes | yes | no | yes |
| Reset credentials | no | no | no | yes | yes |
| Answer tickets | no | yes | yes | yes | yes |
| Approve cards, loans and crypto sends | no | yes | yes | no | yes |
| Toggle per-user features | no | yes | no | no | yes |
| Log in as member | no | yes | yes | yes | yes |
| Manage admins, settings, announcements, branding and site content | no | no | no | no | yes |

Every check runs on the server in a Laravel Policy.

### Admin log in as member

An admin with the permission starts it from the member's page with the button **Log in as member**. The session switches to the member's view, with a permanent banner: "Acting as [member] (staff: [admin]). Exit."

- **Reason required.** The admin picks a reason code and can link a ticket. A row in `impersonations` stores admin, member, reason, ticket, mode, IP, start, end and expiry.
- **Full member actions, with guardrails.** While impersonating, the admin can do what the member can do: transfers, deposits, payees, bill pay, card requests, crypto sends and loan acceptance, under the member's limits and rules. Each money action asks for the admin's own 2FA code (or their password when they have not turned 2FA on), and an admin cannot approve a transaction they started while acting as a member. Password, email and 2FA changes stay blocked, because admins have a separate reset tool. The rules live in `TransferService` and the policies, not in the buttons. A system setting, `impersonation_allow_money_actions`, is on by default, and a Super Admin can switch it off.
- **Limits.** It expires after 30 minutes, an admin cannot impersonate another admin, and starting it needs the member to be active.
- **Full trail.** Every audit row written during the session carries `impersonator_id`. The start and end are audited too.
- **Transparent to the member.** The member's security center lists "Staff accessed or acted on your account" with date and reason.

## 3. Tech stack

Laravel 13 on PHP 8.3 with Livewire 4, so one developer can build both portals without a separate front-end project.

| Need | Choice | Note |
| --- | --- | --- |
| Framework | Laravel 13, PHP 8.3 | Policies, queues, scheduler and encryption are built in |
| UI | Breeze with Livewire and Tailwind | Own Blade layouts for member and admin |
| 2FA | Laravel Fortify (TOTP and recovery codes) | Mandatory for admins |
| Permissions | `spatie/laravel-permission` | Admin titles map to permission presets |
| Audit | `spatie/laravel-activitylog` plus an own `audit_logs` table | Admin actions and impersonation always logged |
| Money | Integer cents with a `Money` value object and cast | Never floats |
| Database | MySQL 8 (InnoDB) or Postgres | Row locks and transactions are required |
| Jobs | Database queue and Laravel Scheduler | Dividends, autopay, scheduled transfers, settlement, auto-close tickets |
| Files | Private disk with signed, expiring URLs | KYC documents, check images, ticket attachments |
| QR codes | `endroid/qr-code` | Crypto fund page |
| PDF | `barryvdh/laravel-dompdf` | Statements and loan schedules |
| Mail | SMTP2GO or Mailtrap | Verification codes, notifications, ticket replies |
| Tests | Pest | Ledger concurrency, policies, impersonation |

## 4. Features by module

### Member core
- Registration with a membership step: field-of-membership check. Joining and opening accounts is free, with no opening deposit. Email verification, TOTP 2FA, password reset.
- Dashboard with Share Savings, Share Draft Checking, Money Market and Certificate accounts, plus announcements.
- Transaction history with search, filters, CSV export and monthly PDF statements.
- Profile and notification preferences.

### Deposits and transfers
- **Deposits by members:** check deposit (front and back images, pending review), ACH pull from a saved external account (pending review, settles next business day), and cash deposit recorded by a Teller.
- **Transfers:** between own accounts, to another member by member number, ACH out, and wires (per-user flag, same-day with a cutoff time and a fee).
- **Payees** have a 24-hour cooling-off period before first use. Scheduled and recurring transfers run from the scheduler.
- **Limits:** per-transaction and daily caps per account, editable by admins with permission.

### Decisions and reasons
- Statuses: `pending`, `held`, `approved`, `rejected`, `completed`, `failed`, `reversed`.
- Rejecting or holding needs a reason code plus text. A hold can carry an expected release date. The member sees both, with a **Contest this decision** button that opens a linked ticket.
- Large amounts need a second admin (dual approval). Every decision is a row in a polymorphic `decisions` table, shared by transactions, cards, loans, KYC and crypto sends.

### Account locks
- An admin locks an account or a whole member with a reason. Types: full lock, debit block, credit block.
- The member can still sign in to read the reason and open a ticket. The block is enforced inside `TransferService`.
- Unlocking needs a note. Every lock and unlock is audited. The member can also lock their own account from the security center.

### Admin balance adjustments
- **Adjust balance** on the member page: credit or debit, amount, a description the member sees on the statement, a reason code and an internal note.
- It posts as an `adjustment` transaction with ledger entries. A debit cannot go below zero without the overdraft permission, and large amounts need dual approval.

### Support tickets
- Members open tickets by category (account, transaction, deposit, card, loan, technical, other) with attachments, and follow a threaded conversation with email notice on each reply.
- Admins get an inbox with filters, assignment, priority, internal notes (never shown to the member), canned replies and a panel for the linked transaction or account with quick actions.
- Statuses: open, awaiting member, awaiting staff, resolved, closed. Resolved tickets auto-close after a set number of days.

### Virtual card (on request)
- A member sees only a **Request virtual card** button until a card exists. Admin approves or rejects with a reason.
- Active cards: reveal number and CVV after step-up verification, freeze, set limits, cancel and replace.
- A **test merchant** page creates authorization holds and settlements through `TransferService`, with declines for frozen cards, limits and low funds.
- Card numbers use a clearly fake test range (prefix `999999`) with a valid check digit (`App\Support\CardNumber`).

### Per-user feature flags
- `user_features` stores which optional features each member has: `crypto`, `loans`, `wires`, `higher_limits` and others. An admin toggles them on the member page, and each change is audited.
- Enforced by route middleware that returns 404 when a flag is off, and by hiding the menu item.

### Crypto (simulated): fund and send
- **Fund:** the admin adds a receive address to a member's wallet (asset, network, address, optional memo). The member's **Fund wallet** page shows it with a copy button, a QR code and a network warning. The admin then **confirms funding** for a simulated incoming deposit, and the wallet balance rises.
- **Send:** the member enters asset, network, destination address and amount. The amount moves to held and the request waits for admin review.
- The admin approves (a simulated transaction hash is generated and the amount is deducted), rejects (the amount is released) or holds, always with a reason. A network fee is set in system settings.
- Admins can also adjust a crypto balance with a description. Every crypto page states that nothing real is held or traded.

### Loans (personal and auto)
- Member applies with amount, term, purpose, income and, for auto, vehicle details. The application moves through submitted, in review, needs info, approved or rejected, with reasons.
- On approval the admin sets the APR and disburses to the member's account. The schedule uses `App\Services\Loans\AmortizationSchedule` (integer cents, last payment absorbs rounding).
- Autopay runs on the due date from a linked account. A late fee applies after a grace period and loans can become delinquent. Members can pay extra and see a payoff amount.

### Bill pay
- Payees can be marked as billers with a category and an account reference.
- A payment has a send date, amount and optional recurrence. Status runs scheduled, processing, delivered, with a delivery estimate. Amounts over a threshold go to admin review.

### Overdraft protection
- A checking account can link a savings account. When a debit would exceed the available balance, the shortfall moves from the linked account first, as an `overdraft_transfer` with an optional fee, and both appear in history.

### Savings goals
- Each goal is a hidden sub-account of a parent account, so the ledger needs no special case. Fields: name, target amount, target date and progress. Optional automatic contributions run weekly or monthly.

### Spending insights
- Card, bill pay and transfer records get a category the member can change. Screens: monthly spend by category, top merchants, income against spending, and optional monthly budgets with a warning near the limit. A scheduled job fills a monthly summary table.

### Security center
- Active sessions and devices with sign-out of other devices, login history, new-device email alerts, 2FA and recovery codes, and a record of staff access to the account.
- A **Lock my account** button for emergencies. Unlocking needs identity verification or a ticket.

### Announcements
- Admins create notices (info, warning, maintenance) with an audience (everyone or members with a feature) and a start and end time. Members can dismiss them.

### Beneficiaries and joint accounts
- **Beneficiaries:** name, relationship, birth date and share per account, primary and contingent tiers, each tier totalling 100%. An admin can mark a member as deceased, which locks their accounts with a reason before staff pay out through documented adjustments.
- **Joint accounts:** the holders table exists from the first migration. The invite, accept and admin-approve screens come last. A lock applies to all holders, and removing a holder needs the other holder's consent or an admin with a reason.

### Admin tools
- **Member 360 page:** accounts, holds, locks, tickets, cards, flags, features, loans and recent audit entries in one view.
- **Fraud flags:** rules for new payee plus large amount, many transfers in a short window, round-number wires and new-country login, feeding a review queue.
- KYC review queue, audit log viewer with filters, and dashboard counters (pending, held, flagged, overdue over three business days, open tickets).

## 5. Public website

Visitors who are not signed in see a full marketing site that looks like a real credit union, built on one shared layout. Rates and product details are read from the database, so an admin can change them without code.

| Page | Route | What it holds |
| --- | --- | --- |
| Home | `/` | Hero with Join and Sign in buttons, product highlights, current rates, security promise, how joining works, public announcements |
| About | `/about` | Member-owned story, mission, fictional leadership, community pages |
| Accounts | `/accounts` | Share Savings, Share Draft Checking, Money Market and Certificates, with dividend rates and minimums from `products`, and an Open free account button |
| Loans | `/loans` | Personal and auto loans, APR ranges from `loan_products`, a payment calculator that uses the same formula as the schedule generator, and an Apply button |
| Rates | `/rates` | One table of dividend rates and loan APRs with an as-of date |
| Digital banking | `/digital-banking` | Tour of transfers, bill pay, goals, insights, virtual card on request and security center |
| Security | `/security` | How accounts are protected, fraud tips, and a report-fraud link to the contact form |
| Join | `/join` | Who can join, three steps, what you need, and a Register button. No opening deposit. |
| Help center | `/help` | Searchable FAQ from `faq_items`, grouped by topic, with a link to contact support |
| Contact | `/contact` | Form (name, email, topic, message), support email and phone. No street addresses. |
| Legal | `/terms`, `/privacy`, `/disclosures`, `/accessibility` | Terms and Privacy written in the member's favor, fee schedule, funds availability, the NCUA statement and the simulation notice |
| Sign in and join | `/login`, `/register`, `/forgot-password`, `/verify-email`, `/two-factor` | Member authentication pages in the same layout |
| Admin sign in | `/admin/login` | Separate page, not linked from the public site, with mandatory 2FA |
| System pages | 403, 404, 419, 500, 503 | Branded error pages and a maintenance page switched by a system setting |

**Rules for every public page**

- One layout with a top navigation (Accounts, Loans, Rates, Digital banking, Security, Help, Sign in, Join) and a footer with legal links and the simulation line.
- Fully responsive from 320 to 1920 px with no sideways scrolling. A browser test visits every route at set widths and fails on overflow.
- Page titles, meta descriptions, a sitemap and a favicon. Plain, accessible markup with labelled forms and visible focus states.
- The contact form is rate-limited and has a hidden honeypot field. Messages go to an admin inbox and are never emailed with raw user content.
- Content is fictional. Names, phone numbers and emails are placeholders, and no page shows a physical location.

**Admin side:** a contact message inbox (new, replied, closed), a rates editor for dividend rates and APR ranges, an FAQ manager, and the maintenance switch. A signed-in member who needs help uses tickets instead of the contact form.

## 6. Branding and site content

An admin with the `site.edit` permission changes the credit union's identity and the text and images of every public page from one **Branding** area, with no code and no redeploy. Super Admin has the permission by default, and it can be granted to Managers.

| Setting | Where it appears |
| --- | --- |
| Name, short name and tagline | Browser tab titles, navigation, footer, Terms and Privacy text, email subjects and headers, PDFs and statements, the 2FA app label |
| Logo for light and dark backgrounds | Navigation, footer, emails, statements. Without an upload the name shows as a text wordmark. |
| Favicon and app icon | Browser tab, bookmarks, home-screen icon |
| Accent color and one neutral | Buttons, links and highlights. A contrast check refuses unreadable color pairs. |
| Support email, phone and support hours | Contact page, footer, email footers |
| Email sender name | The "from" name on mail. The sender address stays in `.env`. |
| Social links and footer text | Footer |

**Page content editor**
- Each public page is a list of **sections**: hero headline and subhead, button labels and links, feature cards, About text, images, security tips. The admin sees a form with labelled fields and a live preview beside it, never raw HTML.
- Field types: short text, long text, rich text (bold, italic, links and lists only), image with required alt text, button (label and link) and repeatable lists.
- **Draft, preview, publish.** Visitors see only published content. Every publish stores a revision, and one click rolls back. A **Reset to default** action restores the seeded text.
- Rates, minimums and APRs are not typed into content. They come from the rates editor.
- **Legal pages:** publishing a change creates a new `terms_version`, and members who agreed to an older version are notified. The footer line that states the project is a simulation is a locked block that no editor can remove.

**Rules**
- The site works before anyone edits it: every section is seeded with default content.
- No view may hard-code the credit union's name. A lint test fails the build when it finds the name in a template.
- Published content is cached and the cache is cleared on publish.
- Every edit, publish and rollback is audited with before and after values.
- Rich text is sanitized against an allow-list. Links must be http, https, mailto or tel. Image uploads are PNG, JPG or WebP only, SVG is refused, and files get generated names and a size limit.

## 7. Database schema

Every money column is an unsigned big integer in cents. Every table has timestamps, and money, decision and audit tables are never hard-deleted.

**Identity and structure**

| Table | Key columns |
| --- | --- |
| `users` | email, password, role (user or admin), member_number (unique, members only), names, phone, dob, address, status, kyc_status, locked_until, last_login_at |
| `member_profiles` | user_id, field_of_membership, membership_opened_at, ssn_last4 (encrypted) |
| `admin_profiles` | user_id, title |
| `products` | code, name, kind (share_savings, share_draft, money_market, certificate), dividend_rate, min_balance, monthly_fee |
| `kyc_documents` | user_id, type, private path, status, reviewed_by, reviewed_at |
| `user_features` | user_id, feature, enabled_by, enabled_at, disabled_at, note |
| `system_settings` | key, value (limits, fees, cutoff times, thresholds, impersonation switch) |
| `brand_settings` | name, short_name, tagline, logo_light_path, logo_dark_path, favicon_path, accent_color, support_email, support_phone, support_hours, sender_name, footer_text, social_links (JSON) |
| `site_pages` | key, route, title, meta_description, status, updated_by |
| `content_blocks` | page_key, block_key, type, label, draft_value (JSON), published_value (JSON), sort_order, is_locked, updated_by, published_at |
| `content_revisions` | content_block_id, value (JSON), saved_by, created_at |
| `media` | path, original_name, mime, size, alt_text, uploaded_by |

**Accounts and money**

| Table | Key columns |
| --- | --- |
| `accounts` | product_id, type (standard or goal), parent_account_id, account_number (unique), nickname, balance, available_balance, held_balance, status, daily_limit, overdraft_account_id |
| `account_holders` | account_id, user_id, role (primary or joint), status, added_at. **Every policy reads this table.** |
| `transactions` | reference (ULID), idempotency_key (unique), type, from_account_id, to_account_id, payee_id, amount, fee, status, initiated_by, impersonator_id, effective_date, memo, member_description, category_id |
| `ledger_entries` | transaction_id, account_id, direction, amount, balance_after. Append-only. |
| `payees` | user_id, name, routing_number, account_number (encrypted), is_biller, biller_category, activates_at, status |
| `scheduled_transfers` | user_id, from_account_id, target, amount, frequency, next_run_at, status |
| `bill_payments` | user_id, payee_id, from_account_id, amount, send_on, status, recurrence |
| `goals` | user_id, goal_account_id, name, target_amount, target_date, auto_rule (JSON) |
| `categories`, `monthly_summaries` | name and icon; user_id, month, category_id, total |

**Decisions, locks and risk**

| Table | Key columns |
| --- | --- |
| `decisions` | decidable_type, decidable_id (polymorphic), decision (approved, rejected, held, released), reason_code, reason_text, decided_by, hold_until |
| `reason_codes` | code, label, applies_to |
| `account_restrictions` | account_id or user_id, type (full_lock, debit_block, credit_block), reason_code, reason_text, locked_by, locked_at, unlocked_by, unlocked_at, unlock_note |
| `fraud_flags` | transaction_id, rule_code, severity, status, resolved_by, notes |
| `audit_logs` | actor_id, impersonator_id, action, subject_type, subject_id, old (JSON), new (JSON), ip, user_agent. No update or delete. |
| `impersonations` | admin_id, user_id, reason_code, ticket_id, mode, started_at, expires_at, ended_at, ip |
| `login_events`, `sessions` | user_id, ip, user_agent, success, new_device, last_activity |

**Support and communication**

| Table | Key columns |
| --- | --- |
| `tickets` | reference, user_id, category, subject, status, priority, assigned_to, transaction_id, account_id, last_reply_at, closed_at |
| `ticket_messages` | ticket_id, author_id, body, is_internal_note |
| `ticket_attachments` | message_id, private path, name, size |
| `announcements`, `announcement_dismissals` | title, body, type, audience, starts_at, ends_at, dismissible, created_by; announcement_id, user_id |
| `contact_messages` | name, email, topic, message, ip, status (new, replied, closed), handled_by, handled_at |
| `faq_items` | topic, question, answer, sort_order, is_published |

**Optional modules**

| Table | Key columns |
| --- | --- |
| `cards`, `card_transactions` | user_id, account_id, status, last4, brand, expiry, encrypted number and CVV, nickname, limits; card_id, merchant, amount, status, decline_reason, transaction_id |
| `crypto_assets` | symbol, name, network, decimals, enabled, optional usd_rate |
| `crypto_wallets` | user_id, asset_id, balance, held |
| `crypto_addresses` | user_id, asset_id, network, address, memo, added_by, status |
| `crypto_transactions` | user_id, asset_id, type (fund, send, adjustment), amount, fee, address, tx_hash, status |
| `crypto_ledger_entries` | crypto_transaction_id, wallet_id, direction, amount, balance_after |
| `loan_products`, `loan_applications`, `loans`, `loan_schedule` | type, apr range, terms, limits; applicant, amount, term, details, status; principal, apr, term, status, next_due; loan_id, installment, due_date, principal, interest, balance_after, status |
| `beneficiaries` | account_id, name, relationship, dob, share_percent, tier, status |

**Migration order:** identity and structure, accounts and holders, transactions and ledger, decisions and restrictions, audit and impersonations, tickets, features, then the optional modules.

## 8. Money engine

One service class, `TransferService`, is the only code allowed to change a balance. Every screen, job and admin action calls it.

1. **Check the rules:** account and member are active, no restriction blocks the direction, the member's feature flags allow the type, limits hold, and the idempotency key has not been used (a repeat returns the first result).
2. **Check the balance:** if funds are short and overdraft protection is on, the shortfall moves from the linked account first, as its own transaction.
3. **Score risk:** fraud rules can flag the transaction. Flagged, large and review-required types become `pending` and the amount is reserved in `held_balance`.
4. **Decide:** an admin approves, rejects or holds, each with a reason. Amounts over the dual-approval limit need a second admin. A rejection releases the reserve.
5. **Post:** inside `DB::transaction`, lock the accounts in ascending ID order with `lockForUpdate()`, write the debit and credit ledger entries, update balances, set `completed`, write the audit row and notify the member.
6. **Settle later:** ACH completes on the next business day and wires before a cutoff, both by scheduled job. Reversals are new transactions with a reason, never edits.

**Rules that hold everywhere**
- Balances are a cache of the ledger. A nightly check recomputes them and reports any difference.
- Locking accounts in a fixed order prevents deadlocks when two transfers cross.
- While an admin is acting as a member, the transaction records the admin as the impersonator, needs the admin's own 2FA code, and is refused if the system switch is off.
- Admin adjustments, dividends, loan disbursements, overdraft transfers and card settlements all use the same service and the same ledger.
- Crypto has its own ledger with the same append-only rule, and crypto sends use the same decision flow.

## 9. Security checklist

- **Authorization:** a Laravel Policy on every model controller, always reading `account_holders`. A Pest test per policy proves member A gets a 403 on member B's account, ticket, card and statement.
- **Admin scope:** each title gets only its own permissions. A Teller cannot lock accounts or approve a card, and the test must show a 403.
- **Passwords and 2FA:** Argon2id hashing, Fortify TOTP, optional two-step verification for staff (recommended, not required), lockout with rate limiting on login, transfers and code entry, and generic error messages.
- **Step-up verification** (password or 2FA code) for new payees, large transfers, card reveal, crypto sends and security changes.
- **Encryption at rest** (`encrypted` casts) for payee account numbers, SSN last four, TOTP secrets and card data. Account numbers are masked in the UI and never logged in full.
- **Atomicity:** `DB::transaction` with `lockForUpdate()` in a fixed order, and idempotency keys on every money request.
- **Input and sessions:** Form Requests on all input, guarded mass assignment, CSRF, HttpOnly, Secure and SameSite cookies, a 10-minute idle timeout for admins, security headers and HTTPS only.
- **Files:** KYC documents, check images and ticket attachments sit on a private disk, are limited by type and size, and are served through signed, expiring URLs. Editable site content is sanitized on save and image uploads refuse SVG.
- **Audit:** rows are written by the service layer and observers, not controllers, and there is no update or delete route. Admin actions, decisions, locks, impersonation start and end, and feature toggles are all logged.
- **Impersonation:** reason required, expiry, blocks on password, email and 2FA changes enforced server-side, the admin's own 2FA code for every money action, no self-approval of what the admin started, no impersonating admins, `impersonator_id` on every row written, and the member can see that staff accessed or acted on the account.
- **Fiction notice:** the footer, Terms and README say that this is an academic simulation, that no real money, securities or cryptocurrency is involved, and that only fake data should be used.

## 10. Build plan

Build in this order and do not start a milestone until the check of the previous one passes. The ledger comes before any screen.

| Milestone | Build | Check before moving on |
| --- | --- | --- |
| M0 Foundation | Scaffold, brand tokens, layouts, a brand service that reads the name, logo and colors from settings, seeders: 4 products, a Super Admin, 10 demo members with accounts | Log in as a seeded member and as the admin |
| M1 Ledger core | `Money` cast, `account_holders`, `TransferService`, ledger, idempotency, locking, polymorphic `decisions`, overdraft logic | 20 simultaneous transfers never overdraw; a repeated idempotency key moves money once |
| M2 Member portal | Registration, email verification, 2FA, dashboard, history and filters, internal transfers, public pages, profile | A new member opens an account for free, transfers money and sees both ledger lines; every public page fits 320 to 1920 px and the contact form lands in the admin inbox |
| M3 Admin core | Admin login with optional 2FA, titles and permissions, member search and Member 360, transaction queue with approve, reject, hold and reasons, locks, balance adjustment, audit viewer, **log in as member** | A held transfer shows its reason to the member; a locked account blocks a debit even when the service is called directly; a transfer made while impersonating is stamped with the admin's ID and needs the admin's 2FA code |
| M3b Branding and content | Branding page (name, logo, favicon, colors, contact details), page content editor with draft, preview, publish and rollback, media uploads, locked simulation line | Change the name and logo and see them in the navigation, tab title, favicon, emails and PDFs; edit a headline, publish, then roll back; a script tag in rich text is stripped |
| M4 Deposits and KYC | Cash, check and ACH-pull deposits, payees with cooling-off, KYC upload and review | A check deposit stays pending, a rejection shows its reason, and Contest opens a linked ticket |
| M5 Tickets | Member ticket pages, admin inbox, replies, internal notes, linked-record panel, email notices | A member never sees an internal note (a test proves it) |
| M6 Flags and card | `user_features`, virtual card with request and approval, test merchant, announcements, security center | A member without the flag gets 404 on a flagged route; a frozen card declines |
| M7 Loans | Products, application, decision, disbursement, schedule, autopay and late fees | Schedule totals match the principal; autopay posts on the due date |
| M8 Member extras | Bill pay, overdraft screens, savings goals, spending insights | Goal contributions appear in the parent account's ledger; budgets warn near the limit |
| M9 Crypto (simulated) | Assets, admin-set receive addresses, Fund wallet page with QR, admin confirm funding, send request, approve, reject or hold | A rejected send returns the amount; a member without the flag cannot reach any crypto route |
| M10 Beneficiaries and joint accounts | Beneficiaries first, then invite, accept and admin approval for joint holders | Both holders see the account; a lock applies to both |
| M11 Hardening and delivery | Rate limits, security headers, full policy test sweep, demo data and script, README with known simplifications, deployment | All Pest tests pass and the acceptance script runs on the deployed site |

**If time runs short, cut in this order:** joint-account screens, spending insights, crypto, loans. Do not cut the ledger, the audit log, reasons on decisions, tickets or the policy tests.

**Simplifications to list in the README:** one routing number for the whole credit union, simulated ACH and wire settlement, fake card number range, simulated crypto with no real wallets or keys, and no real identity verification.

## 10b. Notifications (toasts)

Every success, error and info message in the interface appears as a **toast**, never as an inline banner. One partial (`partials.toasts`) is included by the public, auth and portal layouts. Controllers flash with `->with('status', ...)`, `->with('sent', ...)`, `->with('error', ...)` or `->with('toast', ['type' => 'ok|bad|warn|info', 'message' => ...])`; views can `@push('toast')`; script calls `window.toast(message, type)`. Toasts auto-dismiss (6 s, errors 9 s), pause on hover and focus, close with the button or Escape, announce through `aria-live`, stack at most four, and work without script (server-rendered ones simply stay). Field-level validation messages stay next to their fields. New features must use toasts for their confirmations and failures.

## 11. Content standards

- One brand everywhere: every public page extends `resources/views/layouts/site.blade.php`, so the header, footer, colors and type never drift.
- No wording such as fictional, demo, test, placeholder or sample anywhere in the visible interface. The only project notice is the small legal line at the bottom of the footer.
- Copy reads like an established US retail credit union: heritage, security, branch network, personal and business banking, member trust.
- No claim of federal deposit insurance and no use of real insurance or lender logos.
- Seed data uses realistic US names, believable balances and recognizable merchants (Whole Foods Market, Con Edison, Delta Air Lines). Routing numbers are nine digits with a valid check digit (121478906, in the San Francisco Federal Reserve district range); account numbers are ten to twelve digits. Never "User 1" or "Test Transaction".


## 10c. M4 as built (deposits, payees, KYC)

- **Check deposit:** member photographs front and back (PNG, JPG or WebP, 8 MB, re-encoded, stored privately under a random name). It is a `deposit` transaction with `requireReview`, so it stays pending and nothing is credited until staff approve. A rejection or hold carries a reason code and text the member sees.
- **ACH pull:** from a saved external account, up to the pull limit, pending until reviewed, effective the next weekday. **ACH out** to a saved account uses the normal limits and review threshold.
- **External accounts (payees):** routing number checked with the ABA checksum, account number encrypted, shown only as the last four digits. A new one cannot be used for `payee_cooling_hours` (24). A Manager, Compliance or Super Admin can end the wait early; it is audited.
- **Cash:** recorded by a Teller (`deposits.post`), posts at once. Amounts at or over `cash_review_threshold` wait for a second person and the teller cannot approve their own.
- **KYC:** government ID and proof of address (images or PDF). Staff with `kyc.review` approve or reject with a reason; both approved means `verified`. Unverified members cannot deposit checks or pull from another bank. Documents and check images are served only through `FileController` to the owner and authorised staff, with `no-store`; each staff view is audited. PDFs download rather than open.
- **Contest:** on a rejected or held transaction, "Contest this decision" opens one ticket linked to the transaction (a second click returns the same ticket). Staff see the contest on the transaction page. The inbox, replies and internal notes arrive in M5.
- Limits in `config/bank.php`: `check_deposit_limit`, `ach_pull_limit`, `cash_review_threshold`, `payee_cooling_hours`.

## 10d. Wires and the feature flag (built between M4 and M5)

- **Feature flag:** `user_features` rows turn optional features on and off per member. Staff with `features.toggle` (Manager, Super Admin) switch them on the member page; each change is audited. The `feature:<name>` route middleware returns 404 without the flag, the menu item is hidden, and the service refuses too. Only `wires` exists so far; M6 adds the rest to `App\Services\Features::ALL`.
- **Outgoing wires:** a member with `wires` and a verified identity fills in a wire form of its own (separate from ACH and the saved external accounts): amount, the recipient's name, address, bank, routing number and account number, the purpose and an optional note, then confirms they understand a wire cannot be taken back. A recipient can be saved for the next wire. There is no cooling-off wait; instead every wire is reviewed, and the reviewer sees whether it is the first wire to that account and is expected to call the member back. The amount and the fee are set aside at once. Every wire waits for staff review (the queue shows the full wire details); approval posts the amount and the fee as separate ledger lines, a rejection releases both, and the member can Contest.
- **Fee and cutoff:** `bank.wire_fee` ($25) and `bank.wire_cutoff` (2:00 pm Pacific) are the defaults; a Super Admin edits both on the Settings page and the change is audited. A wire requested before the cutoff on a weekday is sent that business day, otherwise the next weekday.
- Simplification: wires are simulated like all settlement here, and international wires are not offered.

## 10e. M5 as built: support tickets

- **Members:** Support (`/support`) lists their requests; `/support/new` opens one by category (account, transaction, deposit, card, loan, technical, other) with an optional linked transaction and up to 3 image/PDF attachments. The thread (`/tickets/{ref}`) shows only member-visible messages. Replying to a resolved ticket reopens it; a closed ticket takes no replies. Members can mark their own request resolved.
- **Staff** (permission `tickets.answer`): inbox at `/admin/tickets` with tabs (needs a reply, mine, waiting for member, resolved/closed, all), category filter and search; ticket page with the thread, internal notes (yellow, labelled), canned replies from `config/support.php` (tokens `{first_name}`, `{short_name}`, `{phone}`, `{hours}`), assignment, priority, resolve/close/reopen, and panels for the linked member, transaction and account.
- **Statuses:** open, awaiting_staff, awaiting_member, resolved, closed. A staff reply sets awaiting_member and claims an unassigned ticket; a member reply sets awaiting_staff.
- **Internal notes never reach the member:** they are excluded from the member query (`memberMessages`), send no email and do not change status. A test checks the page, the list and the email text.
- **Email:** `TicketReplied` notification on each staff reply. It says there is a reply and links to sign in; the message text is never in the email.
- **Auto-close:** `php artisan tickets:autoclose` (scheduled daily 03:15) closes tickets resolved more than `support.auto_close_days` (7) days ago.
- **Files:** `ticket_attachments`, stored privately via `SecureUpload`; `/files/ticket/{id}` serves owner or staff, staff views audited (`ticket.attachment_viewed`).
- **Audit actions:** ticket.opened, ticket.replied, ticket.note_added, ticket.assigned, ticket.priority_changed, ticket.status_changed.
- Contested decisions (M4) are tickets in the same inbox, priority high, and the admin transaction page links to them.
