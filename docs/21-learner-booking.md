# Learner booking, rescheduling and intelligent availability

## Overview

Instructors control how pupils book through the learner portal. OwnLane calculates genuinely feasible times from working hours, existing lessons, travel constraints, booking rules and pupil context — without exposing the private diary.

## Booking modes

Per organisation (`organisations.booking_mode`):

| Mode | Behaviour |
|------|-----------|
| `manual` | Default after migration. Pupils cannot self-book. |
| `request` | Pupil requests a time; instructor accepts, suggests another, or declines. |
| `instant` | Pupil books a validated slot directly. |

Related settings:

- `learner_reschedule_mode` — same three modes for moving an existing lesson
- `learner_can_cancel` — portal cancellation on/off
- `cancellation_notice_hours` — hours of notice for a free cancel (default 48)
- `cancellation_late_policy` — `decide` (instructor chooses later) or `charge` (auto outstanding)
- `booking_minimum_notice_hours` — default 12 (for booking, not cancellation)
- `booking_advance_weeks` — default 4
- `booking_slot_increment_minutes` — default 30
- `booking_allowed_durations` — optional JSON list; otherwise usual duration from history

## Domain

`lesson_booking_requests` — separate from `lessons` until accepted.

Statuses: `pending`, `counter_proposed`, `accepted`, `declined`, `withdrawn`, `expired`.

Types: `book`, `reschedule` (links `original_lesson_id`).

Requests do **not** hold the slot until acceptance. Acceptance and instant booking revalidate availability transactionally.

## Services

- `LearnerBookingAvailabilityService` — privacy-safe slot API (no other pupils, breaks, or gap scores)
- `LessonBookingRequestService` — create, accept, decline, suggest, instant book, portal cancel

Reuses travel heuristics from `GapMatchingService` patterns. Does not duplicate `GapMatchingService` or `EmptySeatService`.

## API

### Learner portal

- `GET /portal/booking/settings`
- `GET /portal/booking/availability`
- `GET|POST /portal/booking/requests`
- `POST /portal/booking/requests/{id}/withdraw`
- `POST /portal/booking/requests/{id}/accept-counter`
- `POST /portal/lessons/{id}/cancel`

### Instructor

- `GET /booking-requests`
- `POST /booking-requests/{id}/accept`
- `POST /booking-requests/{id}/decline`
- `POST /booking-requests/{id}/suggest`
- `GET /learners/{id}/booking-availability`

## Race protection

`assertSlotAvailable()` checks overlap and throws `409 Conflict` with “That time has just been taken.”

`lockAndAssertNoOverlap()` uses `SELECT … FOR UPDATE` on overlapping scheduled lessons during instant book / accept.

## Cancellation → gap intelligence

Learner portal cancellation updates the lesson to `cancelled` and applies the org cancellation policy:

- Enough notice → settlement `waived`
- Short notice + `charge` → settlement `outstanding`
- Short notice + `decide` → unsettled; instructor settles from the diary (`POST /lessons/{id}/settle-cancellation`)

Short-notice cancels require a reason. Instructor surfaces (`EmptySeatService`, `GapMatchingService`, Morning Brief) run on the instructor side when viewing the diary or cancelled lesson — same as instructor-initiated cancellation.

## Privacy

Availability responses contain only: date, time, duration/price context, learner-appropriate recommendation text. Never other pupils, break labels, travel legs, or gap scores.

## Future payment integration

Instant book creates a normal scheduled lesson with existing finance semantics (credit at completion). Architecture leaves room for `payment_required_before_booking` without changing lesson storage.

## Deliberately deferred

- Push/email notifications for requests (Morning Brief surfaces pending requests today)
- Automatic waiting-list offers to pupils
- Recurring bookings from the portal
- Time-off / recurring break tables (working hours + lessons + travel only today)
- Stripe / pay-before-book
