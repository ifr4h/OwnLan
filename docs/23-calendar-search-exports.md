# Calendar integration, global search & data export

**Status:** Implemented (utility phase)  
**Date:** September 2026

Related: `04-technical-architecture.md`, `06-payments-finance-tax.md`, `18-competitive-baseline-audit.md`.

---

## Calendar subscription (iCal)

### Approach

Instructors connect their usual calendar app via a **private subscription URL**. OwnLane does not perform two-way sync, OAuth, or real-time push. Calendar clients refresh on their own schedule.

### Security

- Feed URL contains a **high-entropy token** (`calendar_feed_token`, 48 characters via `Yii::$app->security->generateRandomString`).
- Token is unique per organisation and stored server-side.
- Public route: `GET /calendar/<token>.ics` — no session auth; token is the credential.
- Instructor can **reset** the link (regenerate) or **revoke** (disconnect).
- Organisation/instructor IDs are never used as authentication.
- Malformed or revoked tokens return `404`.

### Privacy modes

| Mode | Summary | Location |
|------|---------|----------|
| `private` (default) | "Driving lesson" | Omitted |
| `full` | "{Pupil name} · Driving lesson" | Pickup address when set |

### Feed contents

**Included:**

- Scheduled lessons (future window, ~1 year ahead)
- Recently cancelled future lessons (as `STATUS:CANCELLED` so subscribers remove them)
- Confirmed learner self-bookings (same as scheduled lessons)

**Excluded:**

- Pending booking requests
- Private diary intelligence, gap matching, morning brief
- Recurring breaks (deferred — OwnLane remains operational diary)
- Instructor time off (deferred — no time-off domain yet)

### Event identity & timezones

- Stable UID: `ownlane-lesson-{id}@ownlane.app`
- Times emitted with `TZID=Europe/London` (organisation timezone) via `IcsCalendarBuilder`
- BST/GMT transitions handled through PHP `DateTimeZone`

### API

| Method | Path | Auth |
|--------|------|------|
| POST | `/settings/calendar/connect` | Session |
| POST | `/settings/calendar/regenerate` | Session |
| POST | `/settings/calendar/revoke` | Session |
| POST | `/settings/calendar/privacy` | Session |
| GET | `/calendar/<token>.ics` | Token |

---

## Global search

### Scope

Single endpoint: `GET /search?q=`

Searches (max 5 per group):

- Pupils (name, email, phone)
- Lessons (pupil name, date context)
- Payments (pupil, reference, amount)
- Expenses (supplier, category, description)
- Mock tests (pupil name)

Empty query / recent: `GET /search/recent` returns quick actions and recently updated pupils.

### Exclusions

- `private_notes`, `instructor_notes`, `learner_summary` — not indexed
- Cross-tenant results impossible: all queries use `TenantContext::scopeByOrganisation()`

### UI

- Desktop: sidebar search + `⌘K` / `Ctrl+K`
- Mobile: header search icon
- `GlobalSearchDialog.vue` — debounced search, keyboard navigation

---

## Data export

### Export centre

Settings → **Data & exports** (`/settings/data`)

### Individual CSV exports

`GET /exports/download?type=...&from=...&to=...`

Types: `pupils`, `lessons`, `payments`, `expenses`, `mileage`, `teaching_income`

### Accountant pack

`GET /exports/accountant-pack?from=...&to=...`

ZIP containing:

- `payments.csv`
- `expenses.csv`
- `mileage.csv`
- `teaching-income.csv`
- `receipts/` (expense receipt attachments where present)

Summary preview: `GET /exports/accountant-summary`

### CSV safety

`CsvExporter` component:

- UTF-8 with BOM optional
- RFC 4180 escaping
- **Formula injection protection**: values starting with `=`, `+`, `-`, `@` prefixed with `'`

### Sensitive field exclusions

Pupil export excludes: private notes, auth tokens, portal credentials.

Expense export excludes: private storage URLs (receipts bundled separately in accountant pack).

---

## Deliberately deferred

- Google/Microsoft/Apple OAuth calendar sync
- Two-way calendar editing
- Instructor time off in feed
- Recurring breaks in feed
- Teaching resources in global search
- Postcode in pupil search
- Full GDPR account deletion / complete data archive
- Async export job queues (synchronous generation for solo-instructor scale)
