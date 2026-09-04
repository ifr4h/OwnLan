# Companion access — product decision

**Status:** Canonical product rule  
**Beta:** Broad Companion product is **deferred / not currently exposed**

---

## Core account rule

**One learner account = one learner.**

A learner account is personal to the individual learning to drive. It must not become:

- a family account
- a household account
- a shared learner account
- a parent/child shared login
- a spouse shared login
- a generic multi-user account

Do not design functionality that encourages credential sharing.

---

## Why

OwnLane needs reliable identity and provenance. When activity occurs in a learner account, it must belong to that learner. This matters for learning history, progress, interactive learning, private practice, routes, lesson playback, reflections, test journey, future paid functionality, personalisation, security and privacy.

Allowing multiple people to effectively use one learner account would undermine this and could create commercial abuse (multiple people using one learner subscription rather than their own learner relationship).

---

## Do not enable multi-user learner access

Do not add: "Add family member", "Share account", "Household", "Family login", "Parent login to learner app", or anything that effectively gives another person the learner product.

The learner credentials belong to the learner.

---

## Companion is not another learner

If Companion functionality is introduced later, Companion must represent **limited access to specific information or actions** — not access to the learner product.

This distinction must be reflected technically as well as visually. A Companion must never receive the full learner portal with certain navigation items hidden. The API itself must expose only explicitly permitted functionality.

**Companion** may remain internal umbrella domain terminology. User-facing access should describe what the person is actually helping with (e.g. **Payment contact**, **Practice companion**).

---

## Beta decision

For the current beta, do **not** ship the broad Companion product.

Active identities/products:

- **Instructor**
- **Learner**

Broad Companion UI remains disabled (`companion.global.ts` middleware). Companion HTTP endpoints return 404 via `CompanionFeature::ENABLED = false`. Companion is not exposed in learner navigation.

---

## Preserved foundation (dormant)

Do **not** delete:

| Layer | Items |
|-------|--------|
| Database | `companion_accounts`, `learner_companions` |
| Models | `CompanionAccount`, `LearnerCompanion` |
| Services | `CompanionService`, `CompanionContext` |
| Tests | `CompanionServiceTest` (security isolation) |

Mark as **DEFERRED / NOT CURRENTLY EXPOSED**. The engineering cost is preserved for potential future use.

---

## Future model — purpose-limited

### First likely use case: Payment contact

Real-world example: a mother is learning to drive; her adult son handles lesson payments. She remains the learner with her own account. The son does **not** receive access to her learner account. She invites him as a **Payment contact** with his own restricted identity.

**Payment contact may eventually access** (only what is necessary):

- learner display name
- instructor/business name where needed
- remaining lesson/package credit
- outstanding learner amount
- relevant payment history
- payment request / pay on behalf (when supported)
- minimal lesson context to understand a charge

**Payment contact must not automatically access:**

- learner Home, full lesson history, progress, instructor assessment
- reflections, Learn, interactive road learning, lesson playback
- driving routes, private practice, test journey
- teaching resources, instructor notes, learner personal profile
- instructor diary, other learners

These must not be merely hidden menu items — the Payment Contact API must not return them.

**Payment contact ≠ paid learner.** Premium learner features belong to the actual learner. Payment contact access must not create a free second learner experience.

### Second future use case: Practice sharing

Do not automatically restore the full Practice Companion account.

When private-practice functionality develops, initially prefer **learner-controlled temporary sharing**:

- high-entropy signed token
- expiry, revocation, resource-specific scope
- no indexing, rate limiting

Example: learner shares "Roundabout practice" for today — viewer sees only current focus, a specific resource, and an explicitly shared route. They cannot navigate into the learner account.

Introduce a persistent **Practice companion** identity only if real-world usage shows repeated temporary sharing is frustrating. Do not build complexity before evidence requires it.

---

## Identity / provenance

Maintain strict actor provenance. The system must distinguish:

| Actor | Examples |
|-------|----------|
| Instructor | Lesson notes, skill ratings |
| Learner | Reflections, practice logs |
| Payment contact | Payments on behalf |
| Temporary viewer | Scoped share access |
| Future practice companion | Practice notes |
| System | Automated events |

Do not record another person's activity as though the learner performed it.

Examples:

- Payment contact pays → `paid_by_actor = PAYMENT_CONTACT`, not learner
- Practice companion leaves a note → `created_by = PRACTICE_COMPANION`, not learner

---

## Session / authentication principle

Never solve this by letting multiple people sign into the same learner identity.

| Need | Approach |
|------|----------|
| Ongoing access | Separate identity + separate authentication + explicit relationship + server-side scope |
| One-off access | Constrained temporary share |

Do not blur the two.

---

## Security requirements

Preserve and expand automated tests around:

- Payment contact cannot access learner portal APIs
- Payment contact cannot access progress, Learn, playback, routes, private practice, test info (unless explicit future scope)
- Payment contact cannot access another learner
- Revoked access stops immediately
- Temporary share cannot access resources outside scope
- Expired temporary share stops working
- Cross-tenant access is impossible

**Frontend hiding is not sufficient security.** All permissions enforced server-side.

Gate: `CompanionFeature::requireEnabled()` on HTTP endpoints; service-layer tests in `CompanionServiceTest`.

---

## Do not overbuild now

Current priority is the core OwnLane product. Do not:

- restore broad Companion navigation
- build large permission-management UI
- build household / family / multi-learner management
- build Companion messaging, notifications or subscriptions
- polish dormant Companion dashboards

Preserve architecture. Document the decision. Focus on OwnLane differentiators.

---

## Canonical rule

> A person may help a learner. That does **not** make them the learner.  
> A person may pay for a learner. That does **not** give them the learner product.  
> A person may temporarily help with private practice. That does **not** give them access to the learner account.

**One learner. One learner identity. One personal learning journey.**

Additional people receive only the minimum access required to perform the specific job the learner has authorised.

---

## Implementation reference

| Control | Location |
|---------|----------|
| HTTP gate | `apps/api/components/CompanionFeature.php` |
| Companion API | `apps/api/controllers/CompanionController.php` |
| Learner invite API | `PortalController` companion actions (gated) |
| UI gate | `apps/web/app/middleware/companion.global.ts` |
| Unified sign-in | Instructor + learner only (`UnifiedAuthService`) |
| Security tests | `apps/api/tests/Unit/CompanionServiceTest.php` |
| Temporary share (separate) | `TemporaryShareService` — not Companion |
