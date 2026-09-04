# Public profiles & enquiries

**Status:** Implemented  
**Date:** September 2026  
**Extended by:** `25-public-website.md` (branding, services, FAQ, SEO, analytics)

Related: `23-calendar-search-exports.md`, `05-domain-model.md`, `18-competitive-baseline-audit.md`.

---

## Scope

This phase adds the **acquisition layer** for independent instructors — not the OwnLane marketplace.

Includes:

- Public instructor profile (`/instructors/{slug}`)
- Structured learner enquiries
- Enquiry pipeline in the instructor app
- Fit context (area, transmission, availability, diary times)
- Enquiry → pupil conversion without retyping
- Enquiry → first lesson booking

Does **not** include:

- Platform-wide instructor search
- Ranking / recommendations
- Reviews
- Paid leads / commission
- OAuth calendar sync (see `23-calendar-search-exports.md`)

---

## Public profile

### URL

`/instructors/{slug}` — slug is unique, human-readable, never a database ID.

### Visibility

| Status | Behaviour |
|--------|-----------|
| `draft` | Not public |
| `published` | Public + indexable |
| `unpublished` | Not public |

Explicit opt-in. Existing accounts are not auto-published.

### Public fields

Display name, intro, photo, transmission, teaching areas, public pricing, acquisition status, ADI/PDI self-report, vehicle summary (optional).

### Never exposed

Home address, private phone/email, diary, pupils, finance, internal IDs, working hours.

### Acquisition modes

| Internal | Learner-facing |
|----------|----------------|
| `open` | Taking new pupils |
| `limited` | Limited availability |
| `waiting_list` | Waiting list |
| `closed` | Not taking new pupils |

---

## Enquiries

### Table: `enquiries`

Structured fields for filtering and fit — not a single JSON blob.

Statuses: `new`, `contacted`, `waiting`, `accepted`, `declined`, `converted`.

### Public form

`POST /public/instructors/{slug}/enquire`

Protections: honeypot field, rate limiting (5/hour/IP/org), server validation.

### Fit context

Factual flags only — no match scores:

- Teaching area (postcode prefix heuristic)
- Transmission match/mismatch
- Desired start timeframe
- Practical test date if provided
- Suggested diary times (delegates to working hours + existing lessons + availability windows)

### Conversion

`POST /enquiries/{id}/convert` creates a `Learner` using intake-format mapping — name, phone, email, postcode, transmission, availability, experience.

Duplicate detection by email/mobile before conversion.

---

## API routes

| Auth | Route |
|------|-------|
| Public | `GET /public/instructors/{slug}` |
| Public | `POST /public/instructors/{slug}/enquire` |
| Session | `GET /enquiries`, `GET /enquiries/{id}` |
| Session | `POST /enquiries/{id}/convert` |
| Session | `GET/PUT /settings/profile` |

---

## Retention

Declined/unconverted enquiries are retained for instructor context. Automated deletion policy is a privacy follow-up — document before GDPR deletion automation.

---

## Future marketplace boundary

This architecture intentionally reuses:

- Public profile
- Teaching areas
- Acquisition status
- Structured enquiry
- Availability matching

A future marketplace can add discovery/ranking without replacing these primitives.
