# MVP Specification

## Objective

Prove:

> Will instructors move the day-to-day running of their driving business
> into OwnLane and keep using it?

## Success signals

-   activation
-   pupil import
-   lessons scheduled/completed
-   teaching-day usage
-   30/60/90-day retention
-   share of pupils managed in OwnLane
-   balances/payments used
-   learner portal activation
-   existing tools/workflows replaced

Key interview question:

> If OwnLane disappeared tomorrow, how disappointed or inconvenienced
> would you be?

## P0 Instructor/business setup

-   registration/login/logout/password reset
-   organisation creation
-   instructor profile
-   working hours
-   lesson durations
-   pricing
-   timezone
-   cancellation settings
-   permission foundations
-   tenant isolation

## P0 Pupils

-   create/edit/archive
-   contact details
-   pickup addresses
-   private notes
-   upcoming/history
-   outstanding balance
-   prepaid/package hours
-   progress
-   import existing pupils

P1: test date, guardian/emergency details, richer imports/tags.

## P0 Smart diary

-   day/week views
-   create/edit/cancel
-   recurring lessons
-   availability
-   pickup location
-   travel-time display
-   travel conflict warning
-   holidays/time off

P1: calendar sync, test blocks, basic slot calculation, waiting-list
foundations.

## P0 Today cockpit

-   current/next lesson
-   pupil/time/duration
-   pickup
-   payment state
-   navigation shortcut
-   complete lesson
-   book next

## P0 Complete lesson

Target routine admin in roughly 20--30 seconds:

1.  complete
2.  paid/outstanding/package
3.  quick progress
4.  quick notes
5.  optional next focus
6.  book next
7.  done

## P0 Teaching/progress

-   DVSA-aligned skills
-   progress ratings
-   lesson notes
-   history
-   progress summary

P1: next focus, mock tests, previous summary, voice capture.

## P0 Money

-   record payment
-   paid/unpaid
-   balances
-   packages
-   revenue overview
-   outstanding money
-   expenses

P1: categories, receipt upload, profit overview, exports.

No direct HMRC filing in MVP.

## P0 Learner portal

Responsive web:

-   secure access
-   next lesson
-   history
-   progress
-   balance/package

P1: next focus, instructor details, notifications.

## MVP differentiator --- Smart Day

Generic calendars do not understand a mobile instructor's geography.

V1: - travel between consecutive lessons - travel duration -
tight/impossible travel warning - understand nominal diary gaps are not
automatically bookable

Later evolves into scheduling optimisation and Autopilot.

## Explicitly excluded

-   national marketplace
-   direct HMRC filing
-   complex Open Banking
-   sophisticated AI
-   dynamic pricing
-   advanced route optimisation
-   Driving Passport transfer
-   instructor referral network
-   partner marketplace
-   test-cancellation scraping/bots
-   premature multi-school complexity
-   native apps unless validation requires them

## UX standard

Fast, calm, obvious, low cognitive load, minimal duplicate entry.
