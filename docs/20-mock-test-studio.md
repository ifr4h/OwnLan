# Mock Test Studio + Progress 2.0

**Status:** Implemented (September 2026)

## Domain model

### MockTest
Instructor-conducted mock assessment — **not** an official DVSA test.

| Field | Notes |
|-------|-------|
| `lesson_id` | Usually set when mock runs during a lesson |
| `status` | `in_progress`, `completed`, `abandoned` |
| `result` | `pass_standard` or `not_pass_standard` (completed only) |
| Fault counts | Denormalised for quick queries |

A lesson can contain a mock without changing lesson type. The `MockTest` entity is the source of truth for mock-specific data.

### MockTestFault
Individual fault with:
- `fault_type`: `driving`, `serious`, `dangerous`
- `fault_code` / `fault_label`: from `MockFaultCatalogue`
- `skill_id`: maps to `progress_skills` for evidence
- `client_op_id`: idempotent sync (duplicate ops ignored)
- `undone_at`: soft undo during active mock

### LearnerSkillSelfAssessment
Learner-reported confidence per skill — **never** merged with instructor ratings.

Confidence values: `need_more_help`, `still_practising`, `getting_comfortable`, `feel_confident`

## Result rules

UK practical test fault allowance (documented, deterministic):

- **Pass standard:** ≤15 driving faults, 0 serious, 0 dangerous
- **Not at pass standard:** otherwise

Implemented in `MockTestResultRules`. This is a mock result, not a pass/fail prediction.

## Evidence provenance

| Source | Table | Auto-changes instructor rating? |
|--------|-------|--------------------------------|
| Instructor assessment | `learner_skill_progress` | N/A (instructor sets this) |
| Mock test fault | `mock_test_faults` | **No** |
| Lesson practise tag | `lesson_skills` | No |
| Private practice | `private_practice_sessions` | **No** |
| Learner self-report | `learner_skill_self_assessments` | **No** |

Mock faults appear as evidence in Progress 2.0 skill detail. Instructor assessment remains separate.

## API (instructor)

| Method | Route |
|--------|-------|
| GET | `/mock-tests/catalogue` |
| POST | `/lessons/{id}/mock-tests` |
| POST | `/learners/{id}/mock-tests` |
| GET | `/mock-tests/{id}` |
| POST | `/mock-tests/{id}/faults` |
| POST | `/mock-tests/{id}/faults/{faultId}/undo` |
| POST | `/mock-tests/{id}/finish` |
| POST | `/mock-tests/{id}/abandon` |
| GET | `/learners/{id}/mock-tests` |
| GET | `/learners/{id}/progress` |
| GET | `/learners/{learnerId}/skills/{code}` |

## API (learner portal)

| Method | Route |
|--------|-------|
| GET | `/portal/mocks` |
| GET | `/portal/mocks/{id}` |
| POST | `/portal/skills/{code}/self-assessment` |

## Frontend

| Route | Purpose |
|-------|---------|
| `/lessons/{id}/mock` | Active Mock Studio (mobile-first) |
| `/mock-tests/{id}/review` | Post-drive review |
| `/pupils/{id}/mocks` | Mock history + comparison |
| `/portal/mocks` | Learner mock list |
| `/portal/mocks/{id}` | Learner mock detail |

## Test Journey integration

`TestJourneyService` includes `recent_mocks` when a practical test date is set.

Lesson recaps include `mock_test` summary when a completed mock is attached.

## Session behaviour

Password reset invalidates sessions via `auth_key` rotation (see `19-auth-recovery-invites.md`). Mock tests do not affect auth sessions.

## Offline

Server supports `client_session_id` (mock start) and `client_op_id` (fault record) for idempotent sync.

Full IndexedDB outbox for mock faults is **not yet implemented** — faults require connectivity today. Lesson complete offline pattern can be extended in a follow-up.

## Teaching Studio connection

Review mode links fault areas to `/teaching?skill={code}` for “Explain this”.

## What we deliberately did not build

- Readiness percentage / pass probability
- AI driving scores
- GPS fault detection
- Automatic skill downgrades from mock faults
- Official DVSA test booking
