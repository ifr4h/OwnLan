# Competitive baseline audit

**Status:** Canonical product planning reference  
**Date:** September 2026  
**Rule:** Competitive features are the **floor**. Connected, intuitive execution is the **advantage**.

This document classifies OwnLane against a broad modern driving-instructor software baseline. It is an audit and prioritisation guide — **not a build order to execute all at once**.

Related docs: `02-mvp-spec.md`, `03-feature-roadmap.md`, `05-domain-model.md`, `06-payments-finance-tax.md`, `17-companion-access-decision.md`.

---

## Classification key

| Code | Meaning |
|------|---------|
| **A** | Implemented and strong |
| **B** | Implemented but needs improvement |
| **C** | Partially implemented |
| **D** | Not implemented |
| **E** | Intentionally deferred |
| **F** | Should not be built (or not in current strategy) |

---

## Product principles (non-negotiable)

1. **An instructor should rarely tell OwnLane something it already knows.**
2. **Better ≠ more settings.** Better = fewer actions, derived context, one completion updating connected records.
3. **Do not collapse financial events** — purchase ≠ booked ≠ completed ≠ credit consumed ≠ payment received ≠ accounting income (`05-domain-model.md`, `06-payments-finance-tax.md`).
4. **No fake readiness percentages** or unexplained “AI scores”. Gap and continuity suggestions must be deterministic and explainable.
5. **One learner account = one learner** — no broad companion / household access (`17-companion-access-decision.md`).
6. **Cost discipline** — prefer existing domain data, deterministic rules, PWA/offline, OwnLane content over paid SaaS sprawl.

---

## Connected OwnLane model

Information should flow without re-entry at each stage:

```
ENQUIRY → PUPIL → AVAILABILITY → LESSON → TEACHING → PROGRESS
    → PAYMENT → FINANCE → LEARNER RECAP → NEXT FOCUS → NEXT LESSON
```

**Strong today:** lesson complete → finance + progress + recap; teaching resource → lesson → playback/learn; intake → pupil; gap cancel → empty-seat recovery.

**Weakest links:** pupil-list attention signals, finance ↔ diary capacity, learner self-assessment (separate provenance), mock tests → progress, public instructor profile → enquiries.

---

## Executive summary

### Ahead of typical baseline (do not rebuild)

- Diary intelligence: gap matching, travel warnings, cancellation recovery (`GapMatchingService`, `EmptySeatService`)
- Morning brief / next-best actions (`MorningBriefService`, Today `needs_you`)
- Continuity / lesson rhythm (`ContinuityService`)
- Connected complete-lesson workflow (finance settlement, progress, recap CTAs)
- Learner portal depth: playback, learn, routes, journey, practice
- Teaching Studio → lesson → learner pipeline
- Offline lesson completion (IndexedDB + outbox + idempotency)
- Intake → pupil conversion (no retyping)
- Financial domain separation (packages, charges, payments)

### Behind baseline (genuine gaps)

- Mock tests (full workflow)
- No-show lesson status
- Days off / recurring breaks (diary blocks)
- Booking / reschedule requests (learner-initiated)
- Online payments / payment links
- Vehicles, mileage, receipt upload
- Financial goals linked to diary capacity
- Pupil-list attention aggregation (logic exists; UI fragmented)
- Per-skill learner self-assessment (separate from instructor ratings)
- Public instructor website at `/instructors/{slug}` — branding, services, FAQ, SEO, analytics (`25-public-website.md`)
- Broader data export (pupils, lessons, progress)
- Password reset (MVP spec P0) — see `19-auth-recovery-invites.md`

### Intentionally deferred

- Broad Companion product (architecture preserved, HTTP gated)
- Payment contact (first future narrow use case)
- Marketplace, national search, test-cancellation scraping
- Direct HMRC / MTD submission
- Multi-instructor school UI
- Native apps

---

## Area classifications

### Baseline 1 — Diary

| Capability | Class | Evidence / notes |
|------------|-------|------------------|
| Day / week / month views | **A** | `lessons/index.vue`, `LessonService::diary()` |
| Working hours / recurring patterns | **A/B** | Org settings; weekly lesson series only |
| Recurring breaks | **D** | Not modelled |
| Days off / time off | **C** | Working-day flags only; MVP spec P0 gap |
| Create / edit / move / cancel | **A** | Full lesson CRUD |
| No-show | **D** | Statuses: scheduled, completed, cancelled only |
| Completed | **A** | `LessonService::complete()` |
| Pickup | **A** | Per lesson + pupil default |
| Drop-off | **D** | Not in domain |
| Duration / price / pupil / package context | **A** | Finance on complete |
| Payment state on lesson | **A** | Charges / packages |
| Travel time + warnings | **B** | Heuristic postcode provider (`TravelFeasibilityService`) |
| Calendar totals / available time | **C** | Month lesson counts; no “available hours” summary |
| Current-time indicator | **B** | Partial in Today/diary |
| Gap matching (explainable, ranked) | **A** | `GapMatchingService` — no AI scores |
| Cancellation recovery | **A** | `EmptySeatService` on cancel |
| Booking requests / pupil reschedule | **D** | No request model |
| Pupil-visible availability for booking | **D** | Availability used for matching, not self-book |
| External calendar sync | **B** | Read-only iCal subscription feed — `23-calendar-search-exports.md` |

**OwnLane further:** Gap cards with plain-language reasons (cadence, travel, availability) — **A** service layer, **B** UI polish.

---

### Baseline 2 — Pupil CRM

| Capability | Class | Notes |
|------------|-------|-------|
| Full operational pupil record | **A** | `pupils/[id]/index.vue` |
| Search | **B** | Name search only |
| Filter / sort | **C** | Active vs waiting |
| Archive | **A** | |
| Pause / reactivate | **C** | Waiting lifecycle ≈ pause |
| Book / call / money shortcuts | **B** | Strong; no dedicated progress page |
| Pupil-list attention signals | **C** | Brief/continuity exist; not on pupil list |
| Continuity / lesson rhythm | **B** | `ContinuityService`; in Today API, limited UI |

---

### Baseline 3 — Pupil onboarding

| Capability | Class | Notes |
|------------|-------|-------|
| Manual add + CSV import | **A** | |
| Intake / enquiry form | **A** | `IntakeService`, `/join` |
| Enquiry → pupil (no retyping) | **A** | Accept flow |
| Portal invite | **B** | Email when `MAIL_DSN` set; copy-link fallback otherwise |
| Learner self-onboarding (progressive) | **B** | Intake captures goal, confidence, availability |
| Instructor workflows without learner app | **A** | By design |

---

### Baseline 4 — Lesson workflow

| Capability | Class | Notes |
|------------|-------|-------|
| Before lesson context | **A** | Today + lesson page |
| Single completion flow | **A** | Notes, skills, summary, next focus, finance |
| Auto package/credit | **A** | `FinanceService::settleCompletedLesson()` |
| Auto learner recap | **B** | Derived from completion; not separate step |
| Route + resources in flow | **B** | Attachable; not one unified screen |
| Offline complete | **A** | `12-offline-sync.md`; skills offline **C** (excluded V1) |

---

### Baseline 5 — Progress

| Capability | Class | Notes |
|------------|-------|-------|
| DVSA skills + categories + history | **A** | Seeded catalogue, portal evidence |
| Instructor assessment authoritative | **A** | |
| Evidence over readiness % | **F** | Correctly avoided |
| Hide / reorder / rename labels | **D** | |
| Learner self-assessment (separate) | **C** | Practice feeling/note; intake confidence; **no per-skill learner confidence** |

---

### Baseline 6 — Mock test

| Capability | Class | Notes |
|------------|-------|-------|
| Full mock workflow | **B** | Mock Studio, review, history — see `20-mock-test-studio.md` |
| Mock → progress / recap / next focus | **B** | Fault evidence on skills; recap + Test Journey; no auto-downgrade |

---

### Baseline 7 — Payments

| Capability | Class | Notes |
|------------|-------|-------|
| Cash / bank transfer manual | **A** | |
| Packages / prepaid credit | **A** | Domain rules tested |
| Outstanding / history / lesson link | **A** | |
| Void / correction | **C** | Not full partial refunds |
| Online / cards / payment links | **E** | Phase 2 |
| Payment before booking hold | **E** | Needs payments + concurrency design |
| Bank transfer remains supported | **A** | By design |

---

### Baseline 8 — Finance

| Capability | Class | Notes |
|------------|-------|-------|
| Income / expenses / outstanding / month | **A** | `BusinessFinanceService` |
| Tax-year presets | **B** | Reporting aid, not filing |
| CSV export | **B** | Business finance only |
| Receipts | **D** | No receipt field on expenses |
| Mileage | **D** | |
| Financial goals | **D** | Marketing demo only |
| Finance ↔ diary capacity | **D** | High-value differentiator, not built |
| Tax estimate | **E** | Must not present as professional advice |

---

### Baseline 9 — Expenses

| Capability | Class | Notes |
|------------|-------|-------|
| Amount / date / category / notes | **A** | `Expense` model |
| Supplier / receipt / vehicle link | **D** | |
| Categories | **B** | Fixed set (fuel, insurance, car, phone, ads, training, other) |

---

### Baseline 10 — Vehicles

| Capability | Class | Notes |
|------------|-------|-------|
| Multiple vehicles, reg, mileage, lesson link | **D** | No vehicle entity |

---

### Baseline 11 — Mileage

| Capability | Class | Notes |
|------------|-------|-------|
| Mileage log + export + tax-appropriate provenance | **D** | Fuel category only |

---

### Baseline 12 — Learner experience

| Capability | Class | Notes |
|------------|-------|-------|
| Lessons / progress / test / credit / recap | **A** | Full portal |
| Routes / playback / learn / practice / journey | **A** | Major differentiator |
| Booking / reschedule / pay / buy package | **D** | View-only from learner side |
| Mock results in portal | **D** | |

---

### Baseline 13 — Lesson debrief / playback

| Capability | Class | Notes |
|------------|-------|-------|
| Learner recap from completion | **A** | |
| Lesson playback | **A** | `PlaybackService` |
| Instructor replay | **B** | Moments map; not full playback parity |
| Video/audio recording | **F** | Explicit product rule |

---

### Baseline 14 — Teaching tools

| Capability | Class | Notes |
|------------|-------|-------|
| Road board + real-road + save/share | **A** | Teaching Studio |
| Reusable scenarios / step-by-step library | **B** | Templates exist |
| Teaching → lesson → playback → learn | **A** | Connected |

---

### Baseline 15–16 — Resources / Interactive Learn

| Capability | Class | Notes |
|------------|-------|-------|
| Structured + lesson-linked resources | **B** | Works; CMS internal |
| Interactive scenarios | **A** | `ScenarioPlayer`, personalisation |
| Gamification / XP / leaderboards | **F** | |

---

### Baseline 17 — Test journey

| Capability | Class | Notes |
|------------|-------|-------|
| Theory + practical countdown + centre | **A** | `TestJourneyService` |
| Mock history | **D** | |
| Book DVSA test on behalf | **F** | |

---

### Baseline 18–19 — Enquiries / waiting list

| Capability | Class | Notes |
|------------|-------|-------|
| Intake pipeline + accept → pupil | **A** | |
| Waiting list + gap matching | **B** | Waiting pupils in `GapMatchingService`; UI weak |
| Full CRM pipeline statuses | **C** | Simpler than New/Contacted/Converted |

---

### Baseline 20 — Instructor website / profile

| Capability | Class | Notes |
|------------|-------|-------|
| Public profile / booking page | **D** | Settings only; marketing site is OwnLane brand |
| Enquiry → OwnLane | **B** | Intake links; no `/instructors/{slug}` |

---

### Baseline 21 — Calendar integration

| Capability | Class | Notes |
|------------|-------|-------|
| Google / Apple / Outlook | **B** | Secure iCal subscription feed; no OAuth/two-way sync yet |

---

### Baseline 22 — Data export

| Capability | Class | Notes |
|------------|-------|-------|
| Finance CSV | **A** | Individual + accountant pack ZIP |
| Pupils / lessons export | **B** | Settings → Data & exports; progress export deferred |

---

### Baseline 23 — Companion / payment support

| Capability | Class | Notes |
|------------|-------|-------|
| Broad companion | **E** | Gated; `17-companion-access-decision.md` |
| Payment contact (narrow) | **E** | First future use case |
| Second learner via shared login | **F** | |

---

### Baseline 24–25 — Private practice / routes

| Capability | Class | Notes |
|------------|-------|-------|
| Private practice (separate domain) | **A** | Provenance separate from instructor assessment |
| Learner GPS practice recording | **C** | Schema ready; UX deferred |
| Routes (explicit recording, share, playback) | **A** | Major differentiator |
| Infer competence from GPS alone | **F** | |

---

### Baseline 26–28 — Actions / search / quick add

| Capability | Class | Notes |
|------------|-------|-------|
| Morning brief / Today needs you | **A** | `MorningBriefService` |
| Pupil-list / Money-page attention | **C** | Logic exists; fragmented |
| Global search | **B** | Cmd/Ctrl+K; pupils, lessons, payments, expenses, mocks |
| Quick add sheet | **B** | Lesson, pupil, expense, teaching — not payment/enquiry |

---

### Baseline 29 — Offline

| Capability | Class | Notes |
|------------|-------|-------|
| Offline lesson complete + outbox | **A** | |
| Offline diary / booking | **C** | Teaching-day scoped |
| Offline route recording | **C** | Partial |

---

### Baseline 30 — Driving school foundation

| Capability | Class | Notes |
|------------|-------|-------|
| Org tenancy | **A** | |
| Multi-instructor data model | **C** | `instructors`, `lessons.instructor_id` |
| Multi-instructor UI / school admin | **E** | Solo product for beta |

---

### Cross-cutting

| Item | Class |
|------|-------|
| Password reset | **A** | Instructor + learner portal; hashed tokens, 1h expiry; see `19-auth-recovery-invites.md` |
| Auth / registration | **A** |
| PWA | **A** |

---

## Capability matrix

Priority: **β** = beta, **P1** = post-beta high value, **P2** = later, **—** = defer / don’t build

| Capability | Baseline | State | Gap | OwnLane improvement | User benefit | Complexity | Dependencies | Cost | β | P1 | Long |
|------------|----------|-------|-----|---------------------|--------------|------------|--------------|------|---|----|----|
| Diary views + CRUD | Full | **A** | Minor | Current-time, totals | See the week | Low | — | £0 | — | P1 | — |
| Days off / breaks | Blocks | **C/D** | Entity | Simple time-off blocks | Diary reflects reality | Med | Diary | £0 | **β** | — | — |
| No-show | Status | **D** | All | One-tap on lesson | Accurate records | Low | Lesson | £0 | P1 | — | — |
| Gap match + reasons | Ranked | **A** | UI | Full cards on gap tap | Fill slots faster | Low | Exists | £0 | **β** | — | — |
| Cancel recovery | Slot + matches | **A** | Offer flow | Optional pupil message | Recover revenue | Med | Comms | £0 | P1 | — | — |
| Pupil-list attention | Signals | **C** | UI | Aggregate brief on `/pupils` | See who needs action | Low | MorningBrief | £0 | **β** | — | — |
| Continuity rhythm | Cadence | **B** | UI | On pupil + Today | Rebook before drift | Low | ContinuityService | £0 | **β** | — | — |
| Complete lesson (connected) | One flow | **A** | — | Keep consolidating | Less admin | Low | — | £0 | — | — | — |
| Learner self-confidence | Separate | **C** | Per-skill | Recap optional feeling | Useful disagreement | Med | Progress | £0 | P1 | — | — |
| Mock tests | Full | **B** | Mobile studio + review; offline fault queue deferred | Quick mobile capture | Test evidence | High | Progress | £0 | **β** | P1 | — |
| Manual payments + packages | Core | **A** | — | — | Already strong | — | — | £0 | — | — | — |
| Online payments | Cards/links | **E** | All | Stripe when ready | Faster pay | High | Compliance | ££ | P2 | **P1** | — |
| Finance goals ↔ diary | Connected | **D** | All | Deterministic hours-to-goal | Business clarity | Med | Diary+finance | £0 | P1 | — | — |
| Expense receipts | Attach | **D** | Field | Photo attach, no OCR | Tax prep | Med | Storage | £ | P1 | — | — |
| Vehicles + mileage | Multi-car | **D** | All | Light vehicle entity | Accurate costs | Med | Expenses | £0 | P2 | P1 | — |
| Learner portal depth | Checkbox | **A** | Booking | Polish connection | Pupil retention | Low | — | £0 | **β** | — | — |
| Lesson playback | Rich recap | **A** | — | — | Differentiator | Low | — | £0 | — | — | — |
| Teaching Studio | Boards | **A** | Library | More saved scenarios | Explain once | Med | — | £0 | P1 | — | — |
| Interactive Learn | Scenarios | **A** | Content | OwnLane-owned scenarios | Learn between lessons | Med | Content | £0 | P1 | — | — |
| Intake → pupil | Excellent | **A** | — | — | No retyping | — | — | £0 | — | — | — |
| Waiting ↔ gaps | Match | **B** | UI | Matches on waiting view | Convert waiters | Low | GapMatching | £0 | **β** | — | — |
| Public instructor profile | Web | **D** | All | `/instructors/{slug}` + intake | Inbound leads | Med | Intake | £0 | P1 | — | — |
| Calendar sync | iCal/Google | **E** | All | Read-only iCal first | Fewer double-books | Med | — | £0 | P2 | P1 | — |
| Data export | Full records | **C** | Pupil/lesson | CSV exports | Trust, portability | Med | — | £0 | P1 | — | — |
| Payment contact | Narrow | **E** | Product | Separate API surface | Family pays, not learns | High | Payments | £0 | P2 | P1 | — |
| Global search | Cross-module | **D** | All | Cmd+K pupil+lesson | Find fast | Med | — | £0 | P1 | — | — |
| Offline teaching | Critical | **A** | Skills | Extend offline ratings | Field reliability | Med | Syllabus | £0 | P1 | — | — |
| Multi-instructor school | Foundation | **C** | UI | Don’t deepen solo hacks | Future schools | High | — | £0 | P2 | P2 | — |
| Password reset | P0 spec | **A** | — | Separate instructor/portal flows | Account recovery | Low | Auth | £0 | **β** | — | — |

---

## Do not rebuild (preserve)

| Asset | Location |
|-------|----------|
| Financial domain separation | `FinanceService`, finance migrations, `Money.php` |
| Gap matching | `GapMatchingService` |
| Empty-seat recovery | `EmptySeatService` |
| Continuity engine | `ContinuityService` |
| Morning brief | `MorningBriefService` |
| Complete-lesson chain | `LessonService::complete()` |
| Learner portal stack | `PortalController`, portal pages |
| Teaching → playback → learn | `TeachingStudioService`, `PlaybackService`, `LearnService` |
| Offline outbox | `useOfflineTeaching.ts`, `12-offline-sync.md` |
| Intake → pupil | `IntakeService` |
| Companion architecture (dormant) | `CompanionService`, `CompanionFeature::ENABLED = false` |

---

## Three-second rule — current gaps

| Screen | Target | Gap |
|--------|--------|-----|
| Today | Next, where, who, context | **Mostly met** |
| Pupil | Next, last, focus, test, credit | **B** — buried in tabs |
| Money | Earned, owed, position | **Mostly met**; no diary-opportunity link |
| Learner home | Next drive, focus | **A** |

---

## Recommended implementation sequence

### Phase 0 — Beta polish (£0, uses existing backend)

**Goal:** baseline *feel* without new domains.

1. ✅ **IMPLEMENTED** — Surface intelligence on **pupil list** (no future booking, outstanding £, tests soon, continuity).
   - `PupilAttentionService` orchestrates `ContinuityService`, `FinanceService`, `GapMatchingService`
   - `GET /learners` returns `attention` summary + per-row `attention_hint`
   - `GET /learners/{id}` returns `continuity` context above the fold
2. ✅ **IMPLEMENTED** — **Gap cards** with full explain text + one-tap book.
   - Diary day view lists all ranked matches with plain-English reasons and Book CTA
3. ✅ **IMPLEMENTED** — **Waiting list ↔ gap matches** on waiting view.
   - `GET /learners/waiting` enriches rows with `gap_matches` summary
4. **Days off / time off** (MVP spec P0 gap).
5. ✅ **IMPLEMENTED** — **Password reset** (instructor + learner portal).
   - Hashed single-use tokens, 1-hour expiry, session invalidation via `auth_key` rotation
   - Generic responses (no account enumeration)
   - Portal invites: email when `MAIL_DSN` configured, copy-link fallback otherwise
   - See `19-auth-recovery-invites.md`
6. ✅ **IMPLEMENTED** — **No-show** status with explicit charge / waive / package-credit paths.
   - Lesson status `no_show` — distinct from completed and cancelled
   - `POST /lessons/{id}/no-show` with `charge`: `waived` | `outstanding` | `package`
   - No progress, recap, skills or teaching hours for no-shows
   - Cancellation can optionally charge (`charge` on cancel) without completing the lesson
   - Learner portal: neutral “Not attended” label
   - Offline: online-only (finance settlement); idempotent via `client_mutation_id`
7. Three-second rule QA on Today, pupil header, Money.

### Phase 1 — Foundational domain gaps (beta credibility)

8. Optional drop-off (if design partners need it).
9. **Learner self-confidence** (separate provenance, not instructor ratings).
10. **Pupil + lesson CSV export**.
11. **Expense receipt** attach (no OCR).
12. **Instructor pupil progress view** (evidence timeline).

### Phase 2 — Baseline parity (post-beta)

13. Mock test workflow (mobile-first, connects to progress).
14. Public instructor profile + intake (`/instructors/{slug}`).
15. Calendar **read-only iCal feed**.
16. Global search (pupils + lessons).
17. Finance goals ↔ diary hours (deterministic, no income promises).
18. Light **vehicle** entity.

### Phase 3 — Payments & growth (compliance-ready)

19. Stripe payment links + online package purchase.
20. Payment-before-booking hold (only after 19 + concurrency design).
21. **Payment contact** (narrow; not learner portal).

### Phase 4 — Evidence-gated long-term

22. Two-way calendar sync (OwnLane remains source of truth).
23. Mileage log + export (legal review).
24. Multi-instructor school UI.
25. Tax estimate exports (never “we file for you”).
26. Persistent practice companion (only if temporary share insufficient).

### Explicitly do not schedule

- Test cancellation finder (**F**)
- Broad companion / household (**F**, `17-companion-access-decision.md`)
- Readiness % / gamification (**F**)
- Video lesson recording (**F**)
- National marketplace before local density (**E**)
- AI gap scores (**F**)

---

## Definition of success

Do not aim for: *“OwnLane has the same feature.”*

Aim for: *“I tried both and OwnLane was easier.”*

- The instructor feels OwnLane **remembers** things for them.
- The learner feels lessons **connect** together.
- The product becomes more capable **without** becoming more complicated.

**Competitive features are the floor. Connected, intuitive execution is the advantage.**

---

## Code references (audit evidence)

| Area | Key paths |
|------|-----------|
| Diary | `apps/api/services/LessonService.php`, `apps/web/app/pages/lessons/` |
| Gaps | `apps/api/services/GapMatchingService.php` |
| Cancel recovery | `apps/api/services/EmptySeatService.php` |
| Continuity | `apps/api/services/ContinuityService.php` |
| Morning brief | `apps/api/services/MorningBriefService.php`, `apps/web/app/pages/today.vue` |
| Finance | `apps/api/services/FinanceService.php`, `BusinessFinanceService.php` |
| Progress | `apps/api/services/ProgressService.php` |
| Intake | `apps/api/services/IntakeService.php` |
| Portal | `apps/api/controllers/PortalController.php`, `apps/web/app/pages/portal/` |
| Playback / Learn | `PlaybackService.php`, `LearnService.php` |
| Teaching | `TeachingStudioService.php`, `apps/web/app/pages/teaching/` |
| Offline | `docs/12-offline-sync.md`, `useOfflineTeaching.ts` |
| Companion (deferred) | `CompanionFeature.php`, `docs/17-companion-access-decision.md` |
