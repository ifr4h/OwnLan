# Payments 2.0 — Stripe Connect

OwnLane online payments extend the existing finance domain. Stripe is the payment rail; `Payment`, `LessonCharge`, `LearnerPackage` and Accounts remain the business source of truth.

## Architecture

**Stripe Connect Express** with **direct charges** on the instructor's connected account.

- Each organisation has one `InstructorPaymentAccount` (Stripe Express connected account).
- Checkout uses Stripe-hosted Checkout (card, Apple Pay, Google Pay where supported).
- OwnLane may collect an optional `application_fee_amount` via `PaymentPlatformFee` (`OWNLANE_PLATFORM_FEE_BPS`).
- The instructor is merchant of record; OwnLane does not hold instructor funds.

### Why Express + direct charges

- Stripe handles KYC, bank details, payouts and disputes.
- Instructors onboard via Stripe-hosted flows; OwnLane never stores regulated data.
- Fits independent instructors selling lessons directly to learners.

## Domain additions

| Table / model | Purpose |
|---|---|
| `instructor_payment_accounts` | Connect account status per organisation |
| `payment_checkouts` | Scoped checkout sessions with secure tokens |
| `booking_holds` | Short holds for pay-before-book (10 minutes) |
| `package_offerings` | Instructor-defined packages purchasable in the portal |
| `stripe_webhook_events` | Webhook idempotency |
| `payment_audit_log` | Financial audit trail |

`payments` table extended with provider fields (`provider`, `provider_payment_id`, fees, idempotency key, refund fields).

## Payment lifecycle

1. Server creates `PaymentCheckout` with amount derived from `LessonCharge`, package offering, or booking hold.
2. Stripe Checkout session created on the connected account.
3. Learner pays via Stripe-hosted UI.
4. `payment_intent.succeeded` webhook (verified signature) triggers idempotent fulfillment.
5. `FinanceService::applyOnlinePayment()` creates `Payment`, allocates to charges or credits package.
6. Booking holds convert to lessons on successful pay-and-book.
7. Receipt emailed when delivery is configured.

Client redirect to success URL triggers reconciliation as a backstop; webhooks are authoritative.

## Idempotency

- Stripe event IDs stored in `stripe_webhook_events` — replays are ignored.
- Payment idempotency key: `checkout:{checkout_id}`.
- Duplicate fulfillment must not double-settle charges, credit packages, or send receipts.

## Manual payments

Cash and bank transfer recording is unchanged. Online payments are optional per instructor.

## Booking + payment

`organisations.booking_payment_policy`:

- `none` — confirm without payment
- `request_after` — payment requested after booking (instructor flow)
- `require_to_confirm` — instant book requires payment; creates `BookingHold` then checkout

## Security

- Server determines all amounts; never trust client-supplied pence.
- Payment link tokens are unguessable and scoped to one obligation.
- Guest pay links do not grant portal access.
- Tenancy enforced on every payment operation.
- Webhook signatures verified; invalid signatures rejected.

## Environment variables

```
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_API_VERSION=2024-11-20.acacia
OWNLANE_PLATFORM_FEE_BPS=0
WEB_URL=http://127.0.0.1:3000
```

Never expose secret keys to the frontend. Use Stripe test mode locally.

## Refunds

Instructor-initiated full refunds via `PaymentRefundService`. Package refunds blocked when credit has been consumed.

## Deferred (post-beta)

- Partial refunds
- Dispute management UI beyond status awareness
- Payout dashboard in OwnLane (use Stripe Express dashboard)
- Public website package sales
- Payment Contact identity
- Partial online payments
