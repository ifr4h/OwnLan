# Auth recovery and learner invites

**Status:** Implemented (September 2026)

Instructor (`User`) and learner portal (`LearnerPortalAccount`) identities are separate. The same email may exist in both systems without cross-triggering recovery flows.

---

## Password reset

### Endpoints

| Surface | Request | Peek | Confirm |
|---------|---------|------|---------|
| Instructor | `POST /auth/password-reset/request` | `GET /auth/password-reset/peek` | `POST /auth/password-reset/confirm` |
| Learner portal | `POST /portal/password-reset/request` | `GET /portal/password-reset/peek` | `POST /portal/password-reset/confirm` |

### Lifecycle

1. User submits email on forgot-password page.
2. API returns a generic response whether or not the email exists (`PasswordResetService::GENERIC_REQUEST_MESSAGE`).
3. If an account exists (instructor `User`, or activated `LearnerPortalAccount`), a cryptographically random token is created.
4. Token is stored as SHA-256 hash in `password_reset_tokens` — raw token is never logged or persisted.
5. Reset email is sent when `MAIL_DSN` is configured (same transport as invites).
6. User opens link (`/reset-password` or `/portal/reset-password`), peek validates state, confirm sets new password.
7. Token is marked `used_at` and other unused tokens for that account are invalidated.
8. `auth_key` is regenerated on password change — existing sessions are invalidated.

### Expiry

- Reset links expire after **1 hour** (`PasswordResetService::RESET_TTL_SECONDS`).

### Rate limiting

- Per IP: 10 requests / hour
- Per email hash: 5 requests / hour
- Invite generation: 1 per pupil per instructor per **60 seconds**

### Session behaviour

Changing a password regenerates `auth_key`. The user must sign in again after reset (no auto-login). This applies to both instructor and portal accounts.

---

## Learner portal invites

### Instructor flow (pupil record → OwnLane access)

States returned by `PortalAuthService::statusForLearner()`:

| Status | Meaning |
|--------|---------|
| `not_invited` | No portal account row |
| `invite_pending` | Invite issued, not yet activated |
| `invite_expired` | Invite past `invite_expires_at` |
| `connected` | `activated_at` set |

### Lifecycle

1. Instructor invites from pupil record (`POST /learners/{id}/portal-invite`).
2. `LearnerPortalAccount` is created or updated with hashed invite token and 14-day expiry.
3. If `MAIL_DSN` is set: invite email sent (`delivery_mode: email`). Otherwise: copy-link mode (`delivery_mode: link`) — UI shows “Create invite link”, not “Send invite”.
4. Learner opens `/portal/join?token=…`, sets password, account activates.
5. Invite token fields are cleared on activation.

### Re-invite

- Instructors can send a new invite when pending or expired.
- **Connected learners cannot be re-invited** — they must use forgot password.
- A new invite replaces the previous token (old link stops working).

### Invite ≠ password reset

Once activated, portal recovery uses `POST /portal/password-reset/request` only. Re-invite is not a password reset path.

---

## Operational email vs portal identity

The pupil record `email` is operational contact data. `LearnerPortalAccount.email` is the portal login identity.

If an instructor edits a pupil's email after activation, the portal account email is **not** automatically updated. This avoids silently changing login credentials or creating duplicate identities. Account email changes are out of scope for MVP.

---

## Email delivery

`EmailDeliveryService` abstracts transactional email:

- **Production:** set `MAIL_DSN` (Symfony mailer DSN).
- **Optional:** `MAIL_FROM`, `MAIL_FROM_NAME`, `FRONTEND_URL` (link base, default `http://localhost:3000`).
- **Without `MAIL_DSN`:** `canDeliver()` is false — invites use copy-link UX; password reset still returns generic confirmation but no email is sent (user must contact instructor / support in dev).

Do not show “email sent” when delivery was skipped.

---

## Frontend routes

| Route | Purpose |
|-------|---------|
| `/forgot-password` | Instructor reset request |
| `/reset-password` | Instructor reset confirm |
| `/portal/forgot-password` | Learner reset request |
| `/portal/reset-password` | Learner reset confirm |
| `/portal/join` | Learner invite activation |

All are public (no auth middleware).
