# Practical test results + fault trends

**Status:** Implemented (September 2026)

Instructor-logged official DVSA practical test results with structured fault ticks, plus a rolling ADI-style fault analysis report.

## Why

Pass rate alone is a blunt instrument. Instructors already put fault detail into notes after tests; OwnLane stores those ticks so you can see which categories keep coming up over the last 12 months — the same shape as the DVSA ADI driving test data report, without waiting weeks for an email PDF.

## Domain

### PracticalTest

| Field | Notes |
|-------|-------|
| `learner_id` | Pupil who sat the test |
| `test_date` | Calendar date of the test |
| `result` | `pass` or `fail` |
| `test_centre` | Optional |
| `accompanied` | Instructor sat in |
| `examiner_action` | Examiner took physical action |
| Fault counts | Denormalised totals |

### PracticalTestFault

Per category ticks from the sheet:

- `fault_type`: `driving` | `serious` | `dangerous`
- `fault_code` / label / area / aspect from `MockFaultCatalogue`
- `count`: driving faults may be >1; serious/dangerous stored as 1

## Report (rolling window)

Default range: last 365 days in the organisation timezone.

Summary:

- pupils tested, tests taken / passed / failed, pass rate
- average driving / serious / dangerous faults
- examiner action %

DVSA-style indicators (informational only):

| Indicator | Trigger shown |
|-----------|----------------|
| Avg driving faults | 6 or more |
| Avg serious faults | 0.55 or more |
| Examiner took action | 10% or higher |
| Pass rate | 55% or lower |

Charts:

- Horizontal bars by fault category (with Teach link when a skill maps)
- Same data grouped by area
- Top faults from completed mocks in the same window (comparison only)

## API

| Method | Route |
|--------|-------|
| GET | `/practical-tests/catalogue` |
| GET | `/practical-tests/stats?from=&to=` |
| GET | `/practical-tests` |
| POST | `/practical-tests` |
| GET | `/practical-tests/{id}` |
| PUT/PATCH | `/practical-tests/{id}` |
| DELETE | `/practical-tests/{id}` |
| GET | `/learners/{id}/practical-tests` |

Create/update body includes `faults: [{ fault_code, fault_type, count }]` and optional `mark_learner_passed` when result is pass.

## Frontend

| Route | Purpose |
|-------|---------|
| `/accounts/test-faults` | Rolling trends + indicators + bars |
| `/pupils/{id}/tests` | Pupil test history |
| `/pupils/{id}/tests/new` | DL25-style tick sheet |
| `/practical-tests/{id}` | View / edit / delete |

Also linked from Accounts → Reports and the pupil Practical test panel.

## Deliberately not built

- Scraping or importing the official DVSA PDF
- Treating OwnLane indicators as an official standards-check score
- Multi-ADI school aggregation beyond the active organisation
- Auto-changing progress ratings from official test faults
