# Payments, Finance & Tax

## Two separate financial systems

### OwnLane company finances

OwnLane's subscriptions, service fees, permitted platform revenue and
partner revenue belong to OwnLane and form part of its own accounts/tax.

### Instructor finances

Independent instructors run their own businesses. OwnLane may help
organise lesson income, payments, balances, packages, refunds, expenses,
receipts, profit estimates and tax-ready records.

## Positioning

Never market as:

> We monitor your earnings and report you to HMRC.

Prefer:

> Your business. Your records. Your control.

and:

> Your books practically do themselves.

## Operational data is not automatically tax submission data

Scheduled/completed lesson value, received payment and accounting income
are related but not identical.

Legitimate differences include unpaid lessons, refunds, cancellations,
discounts, packages, timing and adjustments.

## MVP

-   record payments
-   balances
-   packages
-   payment history
-   revenue overview
-   expenses
-   receipts
-   simple profit
-   exports

No direct HMRC submission.

## Later payments

Implemented in Payments 2.0 — see `docs/26-payments-2.md`:

-   Stripe Connect Express (card, Apple Pay, Google Pay via hosted Checkout)
-   learner portal checkout for outstanding balance and packages
-   payment request links (guest-scoped)
-   pay-before-book with booking holds
-   refunds (full, instructor-initiated)
-   webhook idempotency and audit trail

Still later:

-   deposits
-   Pay by Bank/Open Banking
-   automatic bank-transfer reconciliation

## Later finance

-   receipt OCR
-   categorisation
-   bank feeds
-   matching
-   tax estimate
-   tax-year summary
-   accountant export
-   accounting integrations

## MTD later

Possible: operational records → bookkeeping records → instructor review
→ totals → explicit approval → compatible MTD/accounting workflow.

Do not imply pupil lists or full lesson histories are routinely sent to
HMRC.

## Payment workflow principle

Payments should disappear into operations.

Package purchase: payment → package balance → lessons deduct → low
balance → top-up prompt → receipts/history synchronised.

## Regulation caution

If OwnLane receives customer funds then passes them to instructors,
payment-services regulation may apply. Prefer authorised
provider/marketplace structures and obtain professional compliance
advice.

## Boundary

Help users maintain accurate records. Never design tools to facilitate
falsifying/concealing taxable income, while also avoiding
surveillance-style product design.
