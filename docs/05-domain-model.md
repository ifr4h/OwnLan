# Conceptual Domain Model

Not a final schema.

## User

Authentication identity.

## Organisation

Tenant/business. Can represent a one-person independent instructor or
future multi-instructor school.

## Membership

User↔Organisation with role: owner, instructor, administrator,
accountant, etc.

## Instructor

Professional teaching/business profile: status, vehicle, transmission,
hours, service area, pricing, availability.

## Learner

Operational pupil record, separate from portal authentication so an
instructor can create a learner before portal signup.

## Location

Home/college/work/custom pickup/test centre. Treat coordinates/addresses
as sensitive.

## Lesson

Organisation, instructor, learner, time, duration, pickup/dropoff,
status, price, payment state, package allocation,
notes/progress/cancellation metadata.

Possible states: scheduled, completed, cancelled, no_show.

**No-show semantics:** A no-show means teaching did not take place. It must
not create progress evidence, skills, learner recap, route playback, or
teaching-hour totals. Financial outcome is separate (`settlement` on the
lesson): waived (no charge), outstanding (amount due), package (explicit
credit consumption), or paid. Instructors choose charge vs waive at mark
time; package credit is never consumed silently.

**Cancellation charging:** Cancelled lessons may optionally create the same
financial outcomes without completing the lesson. Future cancellations may
feed empty-seat recovery; past no-shows do not.

## AvailabilityRule

Instructor time/geographic/duration constraints plus
recurrence/exceptions.

## ProgressSkill

DVSA-aligned skill definition.

## LearnerSkillProgress

Learner + skill + rating + instructor + timestamp + lesson/evidence
link. Preserve useful history.

## LessonNote

Explicitly distinguish private instructor notes from learner-visible
summaries.

## Package

Purchased hours/credits, value, remaining balance and applicable rules.

## Payment

Money recorded/processed. Sources may include card, Pay by Bank,
manually recorded bank transfer/cash, package allocation.

## Expense

Date, amount, category, receipt, notes, accounting state.

## Enquiry

Future CRM/marketplace lead: source, location, transmission,
preferences, desired availability, state, assignment.

## Referral

Future network transfer/referral with explicit learner consent and audit
state.

## Audit

Important for financial adjustments, permissions and sensitive changes.

## Learner portal account

One portal account per learner. Credentials are personal — not shared with
family, household members or payment helpers. See
`17-companion-access-decision.md`.

## Companion (deferred)

Internal umbrella for purpose-limited trusted access (future **Payment
contact**, **Practice companion**). Broad Companion product is not
exposed in beta. Tables `companion_accounts` and `learner_companions`
exist; HTTP and UI are gated. Not a second learner product.

## Temporary share (future practice)

Scoped, expiring share links for one-off practice help — separate from
ongoing Companion identity. See `TemporaryShareService`.

## Key modelling rule

Do not collapse: - lesson scheduled - lesson completed - payment
received - accounting income - package allocation

into one boolean/state. They are related but distinct events.
