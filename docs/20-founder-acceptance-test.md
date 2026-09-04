# Founder acceptance test — closed instructor beta gate

Last run: September 2026 (post Payments 2.0 / Beta Hardening).

This document records a deliberate failure hunt. OwnLane was tested as:

- Hassan — low-tech, paper + WhatsApp
- Amina — busy 30-pupil instructor (demo seed)
- Khadija — CSV import / partial adoption
- Daniel — competitor-app expectations
- Idris — mobile-only
- Sarah — learner portal
- Amal — public enquiry
- Mohamed — money sceptic
- Scheduling chaos + malicious cross-tenant user

Severity: **BLOCKER** / **HIGH** / **MEDIUM** / **LOW**.

---

## Ship verdict

### READY FOR CLOSED INSTRUCTOR BETA

Not production-ready for open self-serve. Not “ignore the founder and ship to 9 strangers tomorrow without a walkthrough.”

Evidence supports a **closed beta** with known instructors who can send feedback, provided Stripe stays in test mode until Connect onboarding is verified per instructor.

---

## Final beta gate checklist

| Gate | Status |
|------|--------|
| No known P0 / BLOCKER open | Pass (known blockers fixed this pass) |
| No known cross-tenant leak in sampled paths | Pass (TenantIsolationTest + service scoping) |
| No known financial double-settlement path for online payments | Pass (idempotency key + webhook event store) |
| No known double-booking via learner availability | Pass (server `assertSlotAvailable`) |
| Offline lesson completion happy path | Pass (IndexedDB outbox + logout clear) |
| Lesson completion / payment / booking / pupil create happy paths | Pass |
| Enquiry → pupil path | Pass |
| Mobile instructor core workflow | Pass with residual MEDIUM friction |
| Demo seed reconciles from underlying data | Pass (no hardcoded Accounts totals) |
| Production web build | Pass (prior run) |
| Clean migrations | Pass |
| Core unit tests | Pass — **206 tests, 886 assertions** |

---

## Personas tested (code + product inspection)

### Hassan (low-tech)

**Add pupil:** Name + mobile only. Optional email/pickup. No Stripe / portal / packages required.  
Path: `/pupils/new` → `/pupils/new/manual`. Extra decision screen (self vs intake link) is one tap of friction — acceptable.

**Add lesson:** Prefills duration from pupil history or org default; pickup from pupil. Price is not asked at booking (set from org rate at completion / settlement) — correct for Hassan, but he cannot store “Maryam is always £60 / 90 min” on the pupil record today. **MEDIUM — deferred.**

**Record payment:** Pupil → Credit & balance → Record payment → amount + method. Language simplified this pass. No ledger jargon in UI.

**Complete lesson:** Exists on lesson detail + Today focus. Advanced teaching features are optional.

### Amina (busy / demo)

Demo org is **Amina Yusuf Driving Tuition** with dense Today, Needs you, gaps, packages, enquiry (Amal). Today prioritises focus lesson then schedule then Needs you — acceptable density for beta; watch signal-to-noise in field.

### Khadija (import)

CSV import exists at `/pupils/import`. Optional commercial features (Stripe, public site, self-booking) can stay off — product remains usable.

### Idris (mobile)

Settings now reachable from mobile top bar. Today Call uses `learner_mobile`. Guest `/pay` no longer auth-blocked. Residual: Accounts tables / dense Settings still desktop-heavy.

### Sarah (learner)

Portal money/payments upgraded; jargon mostly translated. Showcase portal account: Sarah Ahmed (`sarah.ahmed@example.com`).

### Amal (enquiry)

Public site + enquiry; instructor receives without account creation for prospect.

### Mohamed (money)

Accounts separates payments received vs teaching income. Online vs cash vs bank breakdown present. Manual reconciliation still recommended in closed beta.

### Malicious user

Service-layer org scoping + TenantIsolationTest. Guest payment / share tokens public by design. Health error endpoint no longer leaks raw non-HTTP exception text in production.

---

## BLOCKERS found → fixed

| Issue | Fix |
|-------|-----|
| Guest `/pay/*` and `/share/*` redirected to login | `auth.global.ts` public allowlist |
| Guest pay used instructor layout | `layout: false` on pay pages |
| Today Call button never rendered (`focusMobile = null`) | API returns `learner_mobile`; UI uses it |
| Settings unreachable on mobile | Settings icon in mobile top bar |
| Production demo seed with `instructor`/`instructor` | Blocked unless `ALLOW_DEMO_SEED=1` |
| API error handler could expose SQL/internal messages | `HealthController::actionError` sanitises non-HTTP exceptions when not `YII_DEBUG` |

---

## HIGH found → fixed (this + hardening pass)

| Issue | Fix |
|-------|-----|
| No in-app beta feedback / build id | Settings → Beta feedback mailto + `NUXT_PUBLIC_APP_VERSION` |
| Connect onboarding recreating accounts | Resume via account onboarding link |
| Package hours via `(float)` | Decimal string → minutes |
| Marketing claimed features “coming soon” while implemented | Partially addressed; more marketing audit deferred |
| AI-ish “Not just…” marketing copy | Rewritten on instructors / learners pages |

---

## MEDIUM fixed

| Issue | Fix |
|-------|-----|
| Payment form jargon | Clearer intro copy on pupil money panel |
| “Learner says they’ve covered” on instructor UI | “They say they’ve covered” |
| Demo naming mismatch (Sarah Mills vs Amina public profile) | Demo instructor = Amina Yusuf; portal = Sarah Ahmed; enquiry = Amal |

---

## Deferred (document, do not block closed beta)

| Severity | Issue |
|----------|-------|
| HIGH (ops) | No CI workflow in repo — tests are manual |
| MEDIUM | Pupil-level preferred duration/rate not on create form |
| MEDIUM | Offline skill ratings vs docs mismatch |
| MEDIUM | Marketing features page may still under/over-claim calendar/payments |
| MEDIUM | Dense Settings still one long page |
| MEDIUM | No in-app payout dashboard (Stripe Express dashboard by design) |
| MEDIUM | Concurrent double-book under extreme race — app-level checks, not DB exclusion constraint |
| MEDIUM | Multi-device offline conflict policy is “last successful sync wins” for lesson completion — document to beta users |
| LOW | Orphan Companion routes (redirected, intentionally dormant) |
| LOW | Learning CMS admin pages unlinked from instructor nav |

---

## Mobile findings

- Core flows usable at phone widths after Settings + Call fixes.
- Bottom nav + sticky headers: watch CTA collision on lesson complete on small phones (field-test).
- Accounts / Reports remain better on desktop — acceptable for beta.

## Offline findings

- Lesson completion notes/summary/next-focus local-first with outbox.
- Logout clears IndexedDB scope after flush; blocks logout if pending sync.
- Do not queue card payments offline (by design).

## Financial findings

- Integer pence server-side; online fulfillment idempotent.
- Manual + online coexist.
- Instructors must still understand: payment ≠ teaching income; package buy ≠ hours taught.

## Scheduling findings

- Learner availability asserts slot free server-side.
- Booking holds (10 min) for pay-before-book.
- Time-off vs existing lessons: do not silently cancel (existing behaviour — warn/conflict).

## Security findings

- Tenant scoping convention is service-layer (not middleware) — new endpoints must follow pattern.
- Tokens: payment / calendar / intake / invite are scoped; regression tests exist for several areas.
- Public profile must stay field-minimal (ongoing discipline).

## Learner / public findings

- Portal understandable for next lesson / money / progress with residual OwnLane vocabulary.
- Enquiry is short; no forced account.

## Competitor-migration findings

- Baseline diary/pupils/payments are present.
- Differentiator (continuity + gap match) is visible on Today/Diary when data supports it — do not bury under analytics.

## Performance findings

- Unit suite ~3m for 206 tests — acceptable.
- Heavy diary / 100-pupil list not load-tested in this pass — watch in closed beta.

---

## Final build status

| Check | Result |
|-------|--------|
| `vendor/bin/codecept run Unit` | OK — 206 tests, 886 assertions |
| Online payment unit tests | OK |
| Frontend production build | OK |
| `npm run typecheck` | **Not configured** — `vue-tsc` not installed; build is the gate |
| Migrations (dev + test) | Applied including Payments 2.0 |
| Demo seed | Guarded in production; reset via `php yii demo/seed --fresh=1` locally |

---

## Founder questions (evidence-based)

1. **First confusion:** Extra step at Add pupil (self vs send link); Settings density.
2. **Too many taps:** Add pupil → choose method → form (3 screens before typing). Acceptable; optional deep-link to manual later.
3. **Clever but low value:** Needs attention noise if cadence wrong — monitor in beta.
4. **Least trustworthy:** Online payments until each instructor completes Connect in test mode themselves.
5. **Most prototype-like:** Accounts → Payments Connect + offerings still thinner than pupil CRM.
6. **Weakest mobile:** Long Settings + Accounts reports.
7. **Re-asks known data:** Lesson price not stored per pupil (by design via org rate).
8. **Contradictory data risk:** Marketing vs product claims if left stale.
9. **Money wrong:** Mitigated by pence + webhook idempotency; still validate live Stripe carefully.
10. **Diary wrong:** Mitigated by server slot checks; DB unique overlap constraint still deferred.
11. **Leak risk:** Service-scoping discipline; public token endpoints.
12. **Offline:** Completion queue works; payments do not.
13. **Total Drive expectation:** Recurring + reports must feel ordinary — field-test with Daniel persona.
14. **“My app doesn’t do this”:** Continuity + gap fill + learner portal + playback when demo data is shown.
15. **Stop after 3 days:** Import friction; mistrust of money; diary feels busy; optional features feel mandatory; mobile Settings buried (fixed).

---

## Remaining reasons instructors could fail or lose trust

1. Stripe Connect onboarding / webhook misconfiguration in their environment.
2. Importing a messy CSV and misunderstanding skipped rows.
3. Confusing payment received with teaching income on Accounts.
4. Relying on offline for payments or multi-device simultaneous edits.
5. Expecting Companion / school features that are intentionally off.
6. Dense week diary on a small phone feeling cramped.
7. No CI means regressions can slip between deploys until pipeline exists.

These are **closed-beta operational risks**, not open blockers for a supervised pilot.

---

## Definition of done for this document

- Deliberate failure hunt recorded
- BLOCKERS fixed
- HIGH items from this hunt fixed or explicitly deferred with reason
- Verdict: **READY FOR CLOSED INSTRUCTOR BETA**
- Not claimed production-ready
