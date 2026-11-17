# Acceptance walkthrough

Run this on the finished site, local or deployed. Each line is something to do and what you should see. Everything is made-up data. Seeded logins are in the README; the walkthrough uses these:

- Staff: `admin@example.com` (Super Admin), `teller@`, `manager@`, `compliance@`, `support@example.com`.
- Members: `maria.alvarez@example.com` (loan, beneficiaries), `linda.nguyen@` (loan application waiting), `robert.thompson@` (wires on, wire waiting), `angela.brooks@` (crypto), `james.carter@` and `sofia.hernandez@` (joint account), `priya.patel@` (open ticket).

Use two browsers or a private window for member and staff at the same time.

## 1. Public site

1. Open the home page, Accounts, Loans, Rates, Digital banking, Security, Business, Help center, Contact and each legal page. Same header, footer and colors everywhere; no page shows placeholder text.
2. Send the Contact form. A toast confirms it. Staff will see it in Inquiries (step 9).
3. Open Join, register with a new made-up name. An email arrives (in `storage/logs/laravel.log` if mail is not set up). Verify it, sign in, and see the member number.

## 2. Member basics (Maria)

4. Sign in as Maria. The top bar shows the previous sign-in. The dashboard lists accounts, recent activity and the current announcement.
5. Activity: filter by date, search for a merchant, export CSV. The totals match the rows.
6. Transfer $50 from Checking to Savings. A toast confirms; both balances change; the transaction page shows both sides. Submit the same form twice quickly: only one transfer happens.
7. Try to send more than the balance. A clear error toast; nothing moves.
8. Bills: add a biller, schedule a payment for next week, cancel it.
9. Goals: create a savings goal and put $25 in it.
10. Cards: request a virtual card, freeze it, change a limit.
11. Insights: spending by category and a budget; recategorise one purchase.
12. Beneficiaries: the savings account shows 60% / 40% primary and one contingent beneficiary. Change a share to make the total 90%: it refuses to save.
13. Security: turn on two-step verification with an authenticator app, sign out, sign in with a code. Sign out the other sessions.
14. Loans: open the loan. The schedule, rate and autopay are shown. Pay the next installment from Checking.

## 3. Staff review (Teller, Manager)

15. Sign in as Robert (member) and request a $2,000 wire from his checking to a made-up recipient. It says it is waiting for review and the amount and fee are set aside.
16. Sign in as `manager@`. Open Queue, find the wire, open it. Approve it. Robert's balance is final; the wire shows as sent.
17. As `admin@`, post a balance adjustment of $25,000 or more (the dual-approval amount). It waits instead of posting; one other staff member's approval is not enough, a second one posts it; nobody can approve their own request or approve twice. Adjustments from $5,000 up wait for one other person.
18. Reject a transaction without a reason: the form refuses. With a reason: the member sees the reason on the transaction.
19. As Robert, send an international wire (needs the feature, already on): choose a country, enter a SWIFT code and IBAN. A wrong SWIFT country or a bad IBAN is refused; Russia is not offered.

## 4. Staff console (Admin)

20. Member 360 for Maria: accounts, recent activity, cards, loans, crypto, joint accounts, flags and the audit trail for her.
21. Lock one account with a reason. As Maria, a transfer from it is refused with the reason. Unlock it.
22. Adjust a balance with a description and a reason code. A Manager over $5,000 is refused; the Super Admin is not (large ones wait for a second person, as in step 17). The member sees the description, not the staff note.
23. Create a new member account, then **Add account history**: pick a balance, a start date, an end date and "a medium amount". The account now has dated activity that ends on exactly that balance, and `php artisan ledger:verify` still passes.
24. Identity review: approve a document as Compliance.
25. Support: open Priya's ticket as `support@`, reply, mark resolved. Priya sees the reply and gets an email that does not include the message text.
26. Audit: filter for the actions above. Each shows who, what, when and the reason; none can be edited.
27. Log in as Maria from Member 360. A banner shows the session is staff-run; changing a password is blocked; it ends after the time limit or with "Exit".
28. Settings: change the wire fee; Branding: change the primary color and publish a Site content edit, then roll it back.

## 5. Optional services

29. Loans: as Linda, the application shows "in review". As `manager@`, open it under Loans, ask for more information, then approve with a rate. Linda gets emails for each step and the money lands in her account.
30. Crypto: as Angela, open Crypto. Balances are shown in dollars first. Confirm her pending deposit as `manager@` or Compliance; she gets an email. Send a small amount to a valid address; it waits for staff.
31. Joint account: as James, open Joint accounts; his checking has two holders. Invite another member by email, accept as them, approve as staff. Remove a holder; any loan autopay or card tied to them moves or stops, and they are told by email.

## 6. Safety checks

32. Signed out, open `/dashboard` and `/admin`: both send you to sign in.
33. As a member, open `/admin`: you get a 403 page, not the console.
34. Open one member's transaction URL while signed in as another: 404.
35. Five wrong passwords in a minute: the sixth try is refused with a wait message.
36. In the browser's developer tools, a page's response headers include `Content-Security-Policy`, `X-Frame-Options: DENY` and, on HTTPS, `Strict-Transport-Security`.
37. Run `php artisan ledger:verify`: it ends with "Every account matches the ledger." Run `./vendor/bin/pest`: all tests pass.
