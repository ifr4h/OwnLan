# Services & Pricing

Status: **implemented** (MVP catalogue + pricing rules + pupil rates)

## Product intent

Services is the instructor’s commercial catalogue:

- what they sell
- how long it lasts
- what it costs
- who can book it
- who can buy it
- packages / blocks
- pricing variations
- pupil-specific rates
- future rate changes

Conceptual split:

| Area | Owns |
|------|------|
| **Services** | What is sold and how it is priced |
| **Diary** | When it is delivered |
| **Accounts** | What was charged, paid, spent and earned |

## Domain

### `organisation_services`

Bookable / listable offerings (lessons, mock tests, etc.).

| Field | Notes |
|-------|-------|
| `kind` | `lesson`, `mock_test`, `refresher`, `motorway`, `test_day` |
| `status` | `draft`, `active`, `inactive` |
| `price_pence` | Current list price for the service duration |
| `duration_minutes` | Length |
| `visibility_public` | Shown on public website when active |
| `booking_access` | `instructor_only`, `request`, `instant` |
| `is_default` | Used when booking without an explicit service |

### `service_price_changes`

Scheduled list-price changes. Effective price for a service on date D is the latest change with `effective_on <= D`, else `organisation_services.price_pence`.

### `organisation_pricing_rules`

Org-level adjustments (evenings, Saturday, Sunday).

| `adjustment_kind` | Meaning |
|-------------------|---------|
| `add_pence` | Add fixed pence |
| `percent` | Add percent of base (integer percent, e.g. 25) |
| `set_pence` | Replace price for matching slots |

Rules apply after base / pupil / scheduled service price. They do not apply when an explicit per-lesson override is supplied.

### `learner_service_rates`

Pupil-specific prices. Optional `service_id` (null = all lesson-like services). Dated with `effective_from` / optional `effective_to`.

### `package_offerings`

Unchanged prepaid blocks. Managed in the Services UI; sold in the learner portal.

### `lessons.service_id`

Optional FK. Snapshot at book/complete time when known.

## Price resolution

For a lesson at local datetime T, learner L, service S (optional):

1. Explicit `price_pence` / `price` on the request
2. Stored `lessons.price_pence` when already set
3. Active pupil rate for (L, S) or (L, any) covering T’s date
4. Service list price as of T’s date (scheduled change or current)
5. Org `default_hourly_rate_pence × duration`
6. Apply matching active pricing rules (priority / sort order)

Integer pence only. Never float.

Financial concepts stay separate: scheduled ≠ completed ≠ payment ≠ package credit ≠ accounting income.

## Public website

Active services with `visibility_public` sync into `profile_services` (and legacy `profile_public_pricing`). Pupil rates, packages and lesson charges are never exposed publicly.

## Migration from existing config

1. If an org has no rows in `organisation_services`, seed from `profile_services` / `profile_public_pricing`, else from default hourly × default duration as “Standard lesson”.
2. Settings hourly rate remains the fallback when no matching service price exists.
3. Profile “Lessons & prices” editor becomes a link into Services (single source of truth).

## API (instructor)

- `GET /services` — catalogue home payload (services, packages, pricing rules, summary)
- `POST /services` — create service
- `GET|PATCH /services/<id>`
- `POST /services/<id>/price-changes`
- `GET|POST /services/pricing-rules`
- `PATCH /services/pricing-rules/<id>`
- `GET|POST /services/pupil-rates`
- `PATCH /services/pupil-rates/<id>`
- Package offerings remain under `/payments/offerings` and are also surfaced via `GET /services`

## UI

Primary nav: Services between Diary and Accounts.

Home: catalogue sections for Lessons, Packages, Pricing — not an admin table.
Custom OwnLane controls throughout; progressive disclosure for advanced pricing.
