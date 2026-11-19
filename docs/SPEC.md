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

## 10f. M6 as built: virtual card, announcements, security center

- **Card (`/card`):** open to any member with verified identity and a checking account; no flag needed (the request and staff approval are the gate). Statuses: requested, active, frozen, cancelled, rejected. Approval needs `approvals.manage` (gate `staff.approveCards`, Manager, Compliance, Super Admin) and writes a `decisions` row; a decline needs a reason code and a message the member sees. Numbers use the `999999` prefix with a Luhn digit; number and CVV are stored encrypted, and revealing them needs the member's password and is audited. Members freeze, set limits (up to `bank.card_daily_limit` and `bank.card_purchase_limit`), cancel, or replace (new number at once, old holds released). Staff can freeze or cancel from `/admin/cards`.
- **Checkout (removed):** the `/card/pay` demo page and its settle and release routes were deleted. `CardService` still has `authorize`, `settle`, `release` and `pay`, which stand in for a merchant network (used by tests and seed data). Behaviour: a hold reserves money through `TransferService::holdFunds`; completing it releases the hold and posts a `card_purchase` through `execute`. Declines: frozen or inactive card, over purchase limit, over daily limit, not enough available money, account restricted.
- **Announcements:** `/admin/announcements` (Super Admin) with type, audience (everyone or a feature flag), start and end, dismissible or permanent. Members see live ones on the dashboard and can dismiss them.
- **Security center additions:** devices signed in (live sessions only) with sign-out of other devices, and an emergency lock of all the member's accounts. Both ask for the password. Staff unlock through the normal unlock flow.
- Not built yet from the security list: new-device email alerts.

- **Card page revision:** card numbers now start with 4 (16 digits, Luhn). The page shows the card beside a details panel; the checkout link was removed from the page. Members can **request a physical card** for an active card (name, mailing address; one order at a time, cancellable while under review). Staff approve and ship, or decline with a reason, on the Physical card orders tab at `/admin/cards`.

## 10g. M7 as built: loans

- **Flag:** `loans` is a per-member feature (Member 360 → Features). Without it `/borrow` is a 404 and the menu item is hidden; `LoanService::apply` refuses too. The public `/loans` page is unchanged.
- **Products:** `loan_products` seeded by migration: Personal ($1,000 to $50,000, 12-60 months, 8.99% to 16.99% APR) and Auto ($5,000 to $75,000, 36-72 months, 5.49% to 11.99% APR).
- **Member:** `/borrow` lists loans and open applications; `/borrow/apply` has the form with a live payment estimate (auto loans ask for vehicle details). One open application at a time and identity must be verified. A member can answer a "needs info" question or withdraw. A loan page shows the schedule, balance, payoff today, pay next / extra / payoff, and autopay.
- **Staff** (`approvals.manage`, gate `staff.approveLoans`): `/admin/loans` tabs (to review, waiting for member, active loans, all). An application can be approved (staff sets the APR inside the product range and may lower the amount), declined with a reason, or sent back with a question. Approval writes a `decisions` row, builds the schedule with `AmortizationSchedule`, and pays the money in through `TransferService` as `loan_disbursement`.
- **Repayment:** payments post as `loan_payment`. An extra payment lowers principal and the remaining payments are recalculated over the remaining months. Payoff = balance + interest since the last due date (actual/365) + late fees.
- **Autopay and late fees:** `php artisan loans:process` (daily 04:00) takes autopay on or after the due date (a failure is audited), adds the late fee (`bank.loan_late_fee`) after the grace days, and marks a loan delinquent after `bank.loan_delinquent_days`.

## 10h. M8 as built: member extras

- **Savings goals (`/goals`):** each goal is a hidden `goal`-type sub-account of a parent account (`accounts.type = 'goal'`, excluded from account lists, transfer pickers and the dashboard). Saving and taking out are ordinary `transfer` transactions, so they appear in the parent's ledger as "Saved to goal: name". A goal shows as reached at its target, can save automatically weekly or monthly (`php artisan goals:run`, daily), and closing returns the money to the parent.
- **Bill pay (`/bills`):** billers are their own table (name, type, account reference) rather than ACH payees. A payment has a send date and optional weekly or monthly repeat. Sending uses `bill_payment` through `TransferService`; amounts over `bank.bill_review_threshold` ($2,500) wait in the staff queue and the bill follows the decision. Status runs scheduled, review, processing, delivered (or failed, cancelled) with delivery estimated at `bank.bill_delivery_days` business days. `php artisan bills:process` runs daily.
- **Overdraft protection (`/overdraft`):** link or unlink a savings or money market account to each checking account. The existing ledger logic moves only the shortfall and records an `overdraft_transfer`; recent ones are listed.
- **Insights (`/insights`):** per month, spending by category, top merchants, money in against money out, and monthly budgets. Spending counts card purchases, bill payments, cash withdrawals, fees and loan payments; moving money between accounts or banks does not. Categories come from `config/spending.php` (merchant words) and the member can change any transaction's category. A budget warns at `bank.budget_warn_percent` (80%) and when over, on the insights page and the dashboard. `php artisan insights:summarize` (monthly) stores totals in `spending_summaries`.

## 10i. M9 as built: crypto (simulated)

- **Switch:** the `crypto` per-member feature (Features::ALL). Without it every `/crypto` route is a 404 and the menu item is hidden. Staff turn it on from Member 360 like wires and loans, and it can be an announcement audience.
- **Assets** (`crypto_assets`, one row per asset and network): BTC, ETH, USDC (Ethereum), USDT (Tron), LTC and SOL, with decimals, an on/off switch, a hand-set dollar rate (estimates only) and a **network fee per send**. The spec says the fee lives in system settings; it is kept on each asset row and edited on the staff Crypto page, Assets tab (Super Admin), because a fee in ETH and a fee in USDC cannot share one number.
- **Money:** amounts are whole numbers of the smallest unit (`App\\Support\\CryptoAmount` parses and formats text, no floats). Wallets keep `balance` and `held`; `crypto_ledger_entries` is append-only like the cash ledger. `CryptoService` is the only thing that changes a wallet.
- **Fund:** the member opens Fund wallet and picks an asset. If no address is set they press Request an address (a `requested` row). Staff set the address from the Funding tab or Member 360 (format checked per network, optional memo, the old address is retired). The page shows the address with a copy button, a QR code (`endroid/qr-code`, inline SVG) and a network warning. The member then tells us the amount they sent, which creates a pending deposit. Staff confirm it, correcting the amount if needed, and only then does the balance rise. Staff can also record funding directly.
- **Send:** asset, address, optional memo (Solana), amount, an acknowledgement and the member's password (or the staff code while acting as the member). The amount and the network fee move to `held`. The member can cancel while it is still in review. Staff approve (the amount and fee are debited, a random hash is made, a ledger row is written), decline (everything is released) or hold, always with a reason on decline and hold, each recorded in `decisions`. A staff member cannot decide on a send they started while acting as the member, or on their own wallet.
- **Adjustments:** signed, with a description and reason, ledger row and audit entry. A wallet cannot drop below what is held. Managers are limited by the dollar estimate against the usual adjustment limit; an asset with no rate needs a Super Admin.
- **Permissions:** approving sends, confirming funding and setting addresses use `approvals.manage` (`staff.approveCrypto`). Not built: dual approval for large sends, and member email alerts for crypto decisions.
- Member crypto pages carry only a short note on how dollar values are worked out (and the CoinGecko credit). The simulation statement the original spec asked for on every crypto page was removed at the owner's request; the footer and Terms still carry it, and the README must list it under known simplifications.
- **Dollar values and live prices (revision):** members see dollars first, with the coin amount in small text, and type send and deposit amounts in dollars (converted at the current price). Prices come from CoinGecko's keyless public `/simple/price` endpoint (`App\Services\CryptoRates`). They refresh every ten minutes (`crypto:rates`, scheduled), when a member opens a crypto page and the prices are over ten minutes old (tried at most once a minute, never blocks or errors the page), and on demand from the Assets tab. A failed call keeps the last known prices. Each asset has a Live price switch; turning it off lets a Super Admin type a rate. An optional `COINGECKO_API_KEY` in `.env` (a free demo key) raises the rate limit. The member pages credit CoinGecko, as its free terms ask. A past transaction's dollar figure is valued at today's price, a simplification for the simulation.

## 10j. M10 as built: beneficiaries and joint accounts

- **Beneficiaries** (`/beneficiaries`, tables `beneficiaries`): per standard account, up to six people in each of two groups, primary and contingent. Each person has a name, relationship, birth date, whole-number share and group. Saving replaces the whole set in one step: a group that has people must total exactly 100%, at least one primary is required if any are named, the same person cannot appear twice in a group, and old rows are kept as history (`status = removed`) with an audit entry. Any active holder of a joint account can edit. The account page and Member 360 show a summary.
- **Joint holders** (`/joint`, table `joint_requests`): a holder (with a verified identity) invites an existing, active, verified member by email. The invitee sees it on the dashboard and on the Joint accounts page and accepts or declines. An accepted invite waits for staff (`approvals.manage`, staff page Joint accounts), who approve or decline with a reason that both members can read, recorded in `decisions`. Only approval creates the `account_holders` row (role `joint`), so before that the invitee sees nothing of the account. Limits: up to `bank.max_joint_holders` holders per account (default 4), one open invitation per account, standard accounts only.
- **Taking a holder off:** a holder can always leave on their own (they are the person whose consent is needed), as long as another holder stays. If the primary leaves, the other holder becomes primary. To remove the other person, a holder asks and the other person agrees or declines. If they decline, staff can remove them anyway from the Joint accounts page or Member 360, with a reason code and a written reason (audited). Removal cancels that person's cards on the account, closes their savings goals there (the money returns to the account) and cancels their scheduled bills from it. `LoanService::moveOffAccount` also moves that person's loan autopay and any open loan application that used the account to another account they still hold (checking first, then savings); with no other account, autopay is switched off and the application is withdrawn. Each change is audited.
- **Locks apply to everyone on the account:** this was already how `TransferService` works (an account lock, or a lock on any active holder, blocks the account), and tests now prove it for joint accounts. A removed holder (`status = removed`) loses access everywhere because `User::accounts()` and `Account::users()` now read only active holder rows.
- **Marked deceased** (Member 360, `accounts.lock` permission): needs a note. The member's sign-in stops (Fortify's authenticate callback refuses status `deceased`) and sessions are deleted, their cards are cancelled, accounts only they hold get a full lock with the `deceased_member` reason, and joint accounts stay open for the other holder (the deceased is removed as holder). Staff then pay out through documented adjustments, as the spec says.
- Holder count is configurable (`bank.max_joint_holders`, default 4). Removing a holder needs that holder's own consent, or staff with a reason. If the primary leaves, the holder who has been on the account longest becomes primary.

## 10k. International wires (as built)

- A per-member feature, `wires_international`, switched on in Member 360 beside Wire transfers. It needs `wires`. Without it the form shows only US wires and the service refuses an international one.
- Migration `2026_11_30_000020`: `kind` (domestic or international), `country`, `swift` on `wire_recipients` and `wire_transfers`; `routing_number` is now nullable.
- `config/wires.php`: the countries offered, the countries that use IBANs, and the blocked list (CU, IR, KP, SY, RU, BY). A blocked country is refused even if typed by hand.
- Checks: SWIFT/BIC shape, and its country letters must match the chosen country; an IBAN country needs a valid IBAN (`App\Support\Iban`, mod-97, prefix matches); other countries take a 4 to 34 character account number. A check cannot prove the account exists, and the page says the recipient should confirm it.
- Wires abroad are USD only. Fee is separate: `wire_fee_intl_cents` (default $45), editable by a Super Admin in Settings and audited.
- Same review, hold and approval flow as domestic wires. Staff see country, SWIFT/BIC and account last four, with an "International wire" chip.
- Tests: `tests/Feature/Wires/InternationalWireTest.php`.

## 10l. Email notifications (as built)

- One class, `App\Notifications\MemberNotice`: a plain email that says something happened and links to the portal. No amounts, addresses, reasons or messages travel by email.
- Security notices (`security: true`) are always sent: sign-in from a new device, and the terms notice. Everything else follows the member's "Deposits, transfers and payments" email preference.
- Sent for: loan approved, declined, more information needed; joint-account invitation, decision, removal request, holder removed, and the autopay or application changes that follow a removal (from `LoanService::moveOffAccount`); crypto deposit confirmed or declined, crypto send approved, declined or held.
- New device: `login_devices` (migration `2026_11_30_000021`) keeps a hash of the browser per member; the first sign-in is recorded silently, a later unseen browser sends the alert. Staff accounts are not tracked.
- Terms: `php artisan members:notify-terms 2027-01-15` emails every active member.
- Tests: `tests/Feature/Notifications/NoticeTest.php`, plus assertions in the Joint, Loan and Crypto tests.

## 10m. Opening history for a new account (as built)

- Staff with the adjust permission see **Add account history** on Member 360 when the member has an open account with no activity. They enter the balance the account should reach, a start date and an end date (up to today, any date in the past), a note, and whether the account shows as opened on the start date.
- Staff also choose how many transactions: a little, a medium amount or a lot. That sets how often purchases, bills, cash withdrawals and savings deposits happen; the balance still lands exactly on the target.
- `HistoryBuilder` makes the activity from a seed: payroll deposits every two weeks, card purchases, bills and ATM withdrawals on checking; monthly deposits and dividends on savings. The balance never goes below zero and ends on exactly the chosen amount. The same inputs and seed always give the same rows.
- `TransferService::postHistory` writes it, so the ledger stays the only thing that changes a balance. Rows are completed transactions and ledger entries stamped with their past dates in date order, so `ledger:verify` passes. It refuses any account that already has activity, so it can run once per account.
- Limits: $50 to $500,000, with no limit on how far back the start date goes. A Manager is held to the normal adjustment limit. Each transaction is marked "Opening history added by staff" in its memo, which members never see: `Transaction::memberMemo()` shows only a memo the member typed, so staff notes on adjustments and history stay internal. And the audit log records the target, dates, count and seed.
- Tests: `tests/Feature/Admin/AccountHistoryTest.php`.

## 10n. M11 hardening and delivery (as built)

- **Limits:** named limiters `portal` (180 a minute per member), `staff` (300 a minute per staff user) and `auth-forms` (8 a minute per address for sign-up, password reset and resend-verification, applied by `ThrottleAuthForms` because Fortify owns those routes). Sign-in and two-step keep Fortify's five a minute. Money-moving forms keep their own tighter limits.
- **Headers:** `SecurityHeaders` on every web response: CSP limited to this site (inline script and style allowed), `X-Frame-Options: DENY`, `nosniff`, referrer and permissions policies, COOP, HSTS on HTTPS, and `Cache-Control: no-store, private` for signed-in pages. Checked in headless Chrome with no violations on public and member pages.
- **Policy sweep** (`tests/Feature/Security/PolicySweepTest.php`): every protected route as a signed-out visitor, every staff route as a member, every member route as staff, no server error on any route with bad parameters, a frozen matrix of which staff title may open which staff page, and one member against another member's records.
- **Demo data:** `DemoMemberSeeder` plus `DemoFeaturesSeeder` (loan on autopay, application in review, joint account, beneficiaries, funded crypto wallet with a deposit to confirm, ticket, announcement). `DemoSeedTest` checks it reconciles to the ledger and refuses production.
- **Delivery:** `admin:create` for the first staff account on a server; README, `docs/ACCEPTANCE.md` (37-step walkthrough) and `docs/DEPLOYMENT.md`.

## 10o. Sign-in and join pages (as built)

- Every sign-in page (log in, forgot and reset password, two-step challenge, join) uses a split layout: a photo on one side and the form on the other. `layouts/auth.blade.php` takes `art` (file in `public/images`; every sign-in page and join share `branch.jpg`), `art-title` and `art-text`. A missing photo shows the green brand background, and on phones the photo becomes a short banner.
- Join is five steps: Eligibility, About you, Contact, Password, Review. A progress bar (earlier steps can be revisited), per-step checks with plain messages, Enter to continue, a live password checklist, and a review step with a Change link on every line (the Social Security digits are never shown back). The final button posts the same form as before, and the server still validates everything. After a server error the wizard opens on the first step that has one. Without JavaScript the form shows as one long page and still works.

## 10p. Photos and logo shipped with the site

- `public/images` holds the site photos: `hero-1..3.jpg`, `about.jpg`, `help.jpg`, `learn-1..5.jpg` and `branch.jpg` (sign-in and join). The hero photos are mirrored so the people sit on the left, outside the text panel.
- The logo files `logo-light.png`, `logo-dark.png` and `favicon.png` are transparent PNGs. `Brand::logoUrl()` and the favicon route use them when nothing has been uploaded on the Branding screen; an upload always wins.

## 10q. Eligible states are a setting

- `App\Support\Region` holds the 50 states and DC. `Region::codes()` returns the open states from the `region_states` setting (JSON), falling back to `config('bank.region_states')`.
- Settings has a "Where membership is open" checklist (Super Admin). At least one state must stay checked. Changes are audited as `setting.changed`.
- The join form's state list, its validation message and its eligibility sentence read from `Region`. Marketing copy that says "five states" or lists branch states is still plain text on the Home and About pages.

## 10r. Confirming an email without mail

`php artisan members:verify-email someone@example.com` marks an address as confirmed without sending a link. Use it until outgoing mail is set up.

## 10s. Join form: accounts, documents and the welcome page

- The join wizard has seven steps: Eligibility, About you, Contact, Accounts, Documents, Password, Review.
- **Accounts:** `account_choice` is `both` (default), `savings` or `checking`. `AccountOpening::openChosen()` opens them; savings is linked as overdraft cover only when both are opened. Money Market can be opened later from online banking; Certificates and Business accounts are not offered at joining.
- **Documents:** a government photo ID (`id_document`) and proof of address (`address_document`) are required, PNG, JPG, WebP or PDF up to 8 MB. They are stored through `KycService::submit()` inside the join transaction, so an unusable file sends the form back and nothing is created. The member's `kyc_status` is `pending` until staff review them. The Verify your identity page is still there for replacements.
- **Welcome page (`/welcome`):** registration redirects here. A short animated check (five steps, skipped with reduced motion or no script) ends in "Congratulations" with member number, each new account's number, the routing number and balance, and a Go to my dashboard button. It is open for 30 minutes after joining (`welcome_until` in the session); otherwise it redirects to the dashboard. It needs sign-in but not a confirmed email.

## 10t. Business accounts

- **Under the member:** a business is not a separate login. The owner joins as a member and the business accounts sit under that membership. `business_profiles` holds legal name, entity type (sole proprietor, LLC, corporation, partnership, nonprofit), EIN (encrypted, shown only as `••-•••1234`), industry, year started and address. One business per member.
- **Products:** `products.segment` is `personal` or `business`. `BUSINESS-CHECKING` (share draft) and `BUSINESS-SAVINGS` (share savings) are created by the migration and by `ProductSeeder`. Business accounts get a `$50,000` daily limit (`bank.business_daily_limit`), no automatic overdraft link, and can have a debit card like any checking account.
- **Joining:** on the Accounts step the member ticks "I also want business accounts" and gives the business details and which business accounts they want. The Documents step then also asks for a business document. All of it is validated server-side and rolls back together.
- **Verification:** `KycDocument::requiredFor($user)` is government ID and proof of address, plus the business document when the member has a business. The member is `verified` only when every required document is approved.
- **Keeping them apart:** overdraft protection can only link accounts of the same segment, and opening a personal checking never links a business savings.
- **Not built:** opening a business later from online banking, more than one business per member, and extra people who can act on a business account.

## 10u. Business loans

- **Products** (`loan_products.segment = business`, created by migration): Business term loan (7.25% to 13.50% APR, $5,000 to $500,000, 12 to 84 months), Equipment loan (6.50% to 11.99%, $5,000 to $750,000, 24 to 84 months) and Business line of credit (9.00% to 15.00%, limits $10,000 to $250,000, `revolving`). Only members with a business profile see or can apply for them.
- **Application:** same flow and staff queue as personal loans. The form asks for annual business revenue (stored in `annual_income`, `employment = business`) and, for equipment, what is being bought and its cost (`collateral`, `collateral_value`). Staff see the business name, type, masked EIN, years in business and revenue. Business loans pay out to a business account and personal loans to a personal one; mixing them is refused.
- **Term and equipment loans:** approved and amortized exactly like personal loans, with the same payments, extra payments, payoff and autopay.
- **Line of credit:** approval opens the line with nothing owed and moves no money. The owner draws into a business account up to the limit (`LoanService::draw`, posted as a loan disbursement). Interest accrues on the drawn balance only. `LoanService::billLines()` runs in the nightly job and, once a month for any line with a balance, makes a bill of one month of interest plus 1% of the balance (at least $25, never more than what is owed). Paying a bill lowers the balance by its principal. Paying a line off keeps it open. A past-due line cannot be drawn on.
- **Not built:** collateral documents, personal guarantees, rate-by-credit-score pricing, and annual renewal of the line.

## 10v. Adding a business later

- **Page:** `/my-business` in online banking ("Business" in the sidebar, and a link on Open an account). The public `/business` page is the marketing page and is unchanged.
- **Flow:** a verified member who has no business fills in the same details as the join form and uploads one business document. The business is saved with `status = pending` and `requested_accounts`. Nothing opens yet, and the member's own verification is not affected.
- **Approval:** when staff approve the business document in the identity queue, `BusinessService::activate()` marks the business `approved`, opens the requested business accounts (with the business daily limit), audits `business.approved` and emails the member. If the document is turned down the member stays verified and uploads a new one from Verify your identity.
- **After approval:** the page lists the business and its accounts, and offers to open whichever business account the member left out. One business per member.
- **Shared rules:** `App\Support\BusinessRules` holds the validation for both the join form and this page.

## 10w. Photos on public pages

- Every public page banner has a photo on its right side. `page_photo($page)` uses `public/images/pages/{page}.jpg` when it exists and otherwise a site photo as a stand-in (accounts, business, loans, digital-banking, security, about, community, help, rates, careers, contact, appointments, learn). The photo is hidden under 1000px wide, where the pale banner shape shows instead.
- Six pages have an optional photo above the dark side box, shown only when the file exists: `accounts-switching`, `security-report`, `digital-enroll`, `loans-lender`, `business-banker`, `community-scholarship` (all `public/images/pages/{name}.jpg`).

## 10x. Phone layout pass (as built)

An automated check now loads every public, member and staff page at 320, 360 and 390 px wide and flags sideways overflow, cut-off table cells, tap targets under 36 px, text under 12.5 px and form fields squeezed narrow.

Fixed from it:
- Page banners no longer show the pale shape behind the heading on phones.
- Rates and Careers tables turn into labelled cards under 600 px.
- Security settings, Announcements and Site tables stack under 700 px (`.dtable.stack`).
- Link buttons, small buttons and form fields are at least 40 px tall on phones.
- Search rows (admin tickets) put the field on its own full-width line.
- Header logo shrinks under 400 px so the Menu button is not cut off.
- Deposits option cards and the dashboard accounts table fit 320 px screens.

Dashboard cards now sit in shared grid rows (`.dashgrid` with `.mcell`): Deposit accounts beside Quick links, then Recent activity beside a Need help card. Cards in a row share top and bottom edges. On phones the order is accounts, activity, quick links, help.

Card alignment sweep: a script checks every page at 1024, 1280 and 1440 px for cards that sit side by side with slightly different tops or bottoms. Fixed from it: Insights now uses shared rows like the dashboard; `.cols` grids stretch cards to equal height (Verification, Borrow); the Deposits option cards use their own `.choicegrid` class, because the join form's `.choice` style was overriding them.

Full-data layout sweep: with demo data loaded (transactions, loans, a line of credit, wires, a card, goals, a bill, deposits, business profiles, tickets), a crawler visited 297 pages (member, second member, staff) and the phone and desktop checks were rerun. Fixed: Beneficiaries table stacks with labels on phones (`.dtable.stackl`); stat strips shrink and go single column on small phones; grid minimums use `min(..., 100%)` so Goals, Loans and `.cols` fit 320 px; Insights category select fits; admin editor buttons are 40 px tall on phones. `public/js/align-columns.js` (loaded by the portal layout) snaps side-by-side cards in two-column pages (account, card, ticket, loan application, admin member) to the same top and, when close, the same bottom.

## 10y. Email (as built)

All email shares one layout (`resources/views/vendor/mail`, with `App\Support\MailBrand`). Nothing about the credit union is written into a template: the header logo or name, button and link colours, support phone, hours and email, optional footer text and the "we will never ask for your password" line all come from the Branding settings, so a rebrand changes every email. The From name is the Sender name and the From address is the Support email, unless `MAIL_FROM_ADDRESS` is set.

Emails sent (no amounts, balances or reasons ever travel by email; the member signs in for details):
- Confirm your email address, Reset your password (worded to match; both use the layout).
- Welcome (when the email is confirmed).
- Security notices, always sent: password changed, password reset, contact details changed, account restricted, restriction removed, new sign-in.
- ID or address document approved or rejected; business request received; business accounts open.
- A transfer, deposit or wire put under review, approved, or not completed.
- Card request and physical card decisions.
- Loan application needs information, decision, approved; joint account notices; crypto status; support ticket reply; terms update.
Activity emails follow the member's "Deposits, transfers and payments" email preference; security notices ignore it.

Staff with site access can open Admin > Emails to preview the main emails with the current branding and send themselves a test. `php artisan mail:test someone@example.com` does the same from the command line.

Sending uses SMTP2GO over plain SMTP (`MAIL_MAILER=smtp`, host `mail.smtp2go.com`, port 2525; see `.env.example`). The support email must be a verified sender in SMTP2GO. `APP_URL` must be the real public address so the logo and links work.

## 10z. Time zone (as built)

The platform runs on Eastern time (`APP_TIMEZONE`, default America/New_York). Dates and times shown, business dates (`today()`), the daily jobs (scheduled at 03:15 to 05:30), loan due dates and the wire cutoff all use it. The "Last sign-in" time and the audit log column show the zone abbreviation (EST or EDT). Set `APP_TIMEZONE` once, before real data exists: times are saved in that zone, so changing it later shifts the times already stored. The Customer Care hours shown on the dashboard, Contact and Help pages now come from the Branding setting instead of fixed text, so they can be written in Eastern time there.

## 10aa. Fresh platform and setup wizard (as built)

A new copy of the platform contains no demo data: no members, no staff, no transactions. The database holds only reference data (deposit products, reason codes, loan products, crypto assets, staff roles and permissions). `DatabaseSeeder` seeds reference data only. The old demo credit union (staff, ten members, a loan, wires, a card and so on) lives in `DemoDataSeeder`, which is opt-in (`php artisan db:seed --class=DemoDataSeeder`) and refuses to run in production.

First-run wizard (`/setup`, `App\Http\Controllers\SetupController`, `App\Support\Installer`, `App\Http\Middleware\RequireSetup`). Until setup finishes, every page redirects to it and it works with no database (sessions and cache use files, and an app key is made on first visit). Steps, each unlocked by the one before: owner key (a code read from `storage/app/install-key.txt`; skipped only when the browser is on the server itself, not when it arrives through a proxy; five wrong tries lock it for 15 minutes), server check, credit union (name, short name, descriptor, tagline, web address, support email, phone and hours, routing number checked against the ABA checksum, optional NMLS number, brand and highlight colours with the same contrast rule as Branding, time zone), database (SQLite file or MySQL, connection tested), email (SMTP details with a reachability test, or "later" which logs mail), first Super Admin (12+ characters, mixed case, a number), and a review step. Install writes `.env`, runs the migrations, loads reference data, saves the Branding row, creates the Super Admin and writes `storage/app/installed.lock`. After that `/setup` returns 404. A site that already has accounts is treated as installed and gets the lock written automatically. A failed install changes nothing that blocks a retry (leftover tables are wiped when there are no accounts).

Settings that were hardcoded are now Branding settings: routing number (footer, help, account details, welcome page) and the optional NMLS number (footer, hidden when empty). The shipped Golden West logo files are used only when the name is the built-in one; any other name gets the text wordmark until a logo is uploaded in Admin > Branding.

`php artisan platform:reset` erases everything, including uploads, and brings the wizard back (local and staging use). `docs/DEPLOYMENT.md` covers the server steps. Tests: `SetupWizardTest`.

Form errors: a form that comes back with field errors also raises one red toast (the message itself when there is one error, "Please fix the highlighted fields." when there are several), in `partials/toasts`. The message stays beside its field, and `toast.js` scrolls the first error into view. No second toast is added when an error toast is already showing. Tests: `ErrorToastTest`.

## 10ab. Updates from GitHub (as built)

Admin, System (`App\Http\Controllers\Admin\SystemController`, Super Admin only through `staff.manageAdmin`, password confirmation before it opens and before install or restore) shows the installed version (`version.json`), checks the Releases page of `config('updates.repo')` and installs the newest release. The GitHub job "Build release zip" (`.github/workflows/release-zip.yml`, run by hand from the Actions tab) runs `scripts/make-release.php` twice and publishes `goldenwest-release.zip` (standard layout) and `goldenwest-release-public_html.zip` (a `public_html` folder and an `app` folder). `App\Services\Updater` picks the zip for the site's layout (public path different from `base/public` means public_html), checks GitHub's sha256 digest, rejects unsafe paths and packages without `version.json`, `artisan` and `vendor/autoload.php`, then works as a saved job in short steps (download, compare by size and CRC, write) so a shared host's request limits are not hit. Each changed file is copied to `storage/app/update-backups/<time>` first; a failed write restores the backup. Never written: `.env*`, `storage/`, `bootstrap/cache`, `public/storage`, `.user.ini`, `*.sqlite`, and an existing `public/.htaccess` (cPanel keeps PHP settings there). The last step runs on the new code: migrations, cache clears, site back up. Visitors see the maintenance page only during the write step. Audit actions: `system.update`, `system.restore`, `system.update_cancelled`. Tests: `UpdaterTest`.

## 10ac. Super Admin is never held (as built)

A transaction started by a Super Admin, or by a member while a Super Admin is acting as them ("log in as member"), is never held for staff review: no $5,000 review threshold, no wire review, no check-deposit review, no second-person cash review. A Super Admin's approval of any held transaction is final on its own (no second approver at $25,000) and a Super Admin may approve something they started. Implemented in `TransferService::needsReview` and `approveInner`, `DepositService::cash`, `StaffDirectory::isSuperAdmin` and the queue screen's approver count. Not changed: balance, limit, restriction and frozen-account checks, and recording. Every such transaction is posted to the ledger and the audit log in full, with the Super Admin named as the one who acted. Other Super Admin approvals (cards, loans, identity review, crypto, joint accounts) are unchanged. Tests: `SuperAdminBypassTest`.

### 10ad. Super Admin on cards, identity, crypto, loans and joint accounts

- A Super Admin acting as a member (log in as member) is approved at once for: a card request, a physical card order, an identity document, a crypto deposit and a crypto send. Other staff still wait for a review.
- A Super Admin's own decision is never blocked by the rule that stops staff deciding on a request they made or that involves them (crypto and joint accounts).
- The member's Security page does not list a Super Admin's access. Other staff access is still listed. The staff audit log records every case.
- Limits, balance checks and the audit trail are unchanged.

### 10ae. Wire limits and security questions

- **Wires:** an outgoing wire has no per-transaction cap and no daily limit, and it does not count toward the daily limit of other transfers. The balance check and the staff review and second-approver rules still apply.
- **Security questions:** a member picks 3 of 15 questions at registration (answers stored hashed after lower-casing and dropping punctuation). After the password, one of the 3 is asked at random, never the one asked at the previous sign in. Five wrong answers pause the sign in for 15 minutes.
- **Unusual sign in** (new device, new network, or 3 or more wrong passwords in the last hour) also needs a 6-digit code emailed to the member (valid 10 minutes). The first sign in ever is not unusual.
- Existing members are asked to set their questions at their next sign in. Staff are never forced; if a staff member sets questions they are asked like a member.
- A Super Admin turns the feature on or off in Admin > Settings. Off asks nothing and keeps saved answers. Members change their questions on the Security page after confirming their password.
- Config: `config/security.php`. Tables: `user_security_answers`, `login_ips`; `users.security_last_question`.
