# Cursor / AI Coding Rules

## Stage

OwnLane is MVP-stage. Do not implement later-phase ideas merely because
they exist.

Before a feature, identify its phase, priority and dependencies.

## Workflow over feature count

Prefer one action updating all dependent records. Avoid duplicate forms
and unnecessary admin.

## Mobile

Core instructor workflows must be excellent on phones, but never
encourage interaction while driving. Assume active use while safely
parked.

## Desktop

Finance/reports/settings should be genuinely desktop-friendly.

## Tenancy

Every business object is organisation-scoped. Never fetch by ID without
tenant access checks.

## Permissions

Enforce server-side, not via hidden frontend controls.

## Business events

Keep lesson scheduled, lesson completed, payment received, accounting
income and package allocation distinct.

## Money

Use integer minor units or exact decimal handling; never binary floating
point. Audit important changes.

## Time

Handle timezone, recurrence and DST correctly.

## Privacy

Explicitly separate instructor-private, learner-visible,
guardian-visible, admin and support data.

## AI

AI removes admin and surfaces suggestions; it does not replace
instructor professional judgment.

Good: speech→notes, summarisation, structuring, opportunity detection.\
Bad: opaque high-stakes decisions, hallucinated finance/tax,
uncontrolled booking/pricing changes.

## Autopilot

Policy-constrained, explainable, auditable and reversible/configurable
where practical.

## Architecture

Modular monolith first. No microservices without measured need.

## Providers

Abstract payment/maps/messaging/accounting providers where lock-in would
be expensive.

## Testing priorities

-   tenant isolation
-   permissions
-   recurrence
-   travel conflicts
-   balances/packages
-   payment transitions
-   lesson completion
-   progress visibility
-   timezone/DST

## Domain language

Use Organisation, Instructor, Learner, Lesson, Availability, Package,
Payment, Expense, Progress, Enquiry.

## MVP filter

Ask:

> Does this make OwnLane materially more useful during an instructor's
> normal working week?

If not, it likely belongs later.
