# Offline sync & PWA (V1)

OwnLane’s instructor app is an installable Progressive Web App. Critical
teaching actions are local-first: the UI updates from IndexedDB immediately,
and an outbox synchronises to the API when connectivity returns.

## Responsibilities

| Layer | Role |
|-------|------|
| Service worker | App shell (HTML/JS/CSS/icons). **Never** caches `/api/**`. |
| IndexedDB | Operational teaching data + pending mutations for the signed-in organisation. |
| Cookie session | Authentication (unchanged). No auth secrets in IndexedDB. |

## What is cached

After a normal online open of Today:

- today’s lessons
- pupils referenced by those lessons (profile, pickup, next focus, last summary)
- individual lesson records opened from that day

We do **not** replicate the organisation’s entire database.

## Offline-capable writes (V1)

- complete lesson
- private instructor notes
- learner-visible summary
- next focus

Structured DVSA skill ratings are **not** in V1 (no approved syllabus list yet).
Text next-focus / summary is the operational progress context for offline.

## Outbox states (internal)

`pending` → `syncing` → removed on success, or `failed` / re-`pending` on
transient errors.

Instructor-facing copy only:

- Offline · changes will sync automatically
- Back online · syncing…
- Saved on this device / Waiting to sync
- Sign in again to sync saved changes

Never claim data is on the server when it only exists locally.

## Idempotency

Each offline completion carries a `client_mutation_id` (UUID). The API stores
it uniquely per organisation and returns the existing completed lesson on
replay — retries must not double-complete.

## Conflict rules (V1)

Most OwnLane instructor data is single-writer operational data.

1. **Complete replay (same `client_mutation_id`)** — return existing row; no
   conflict.
2. **Complete when lesson already completed by another mutation** — treat as
   success for the status flip; do not wipe existing notes/summary/next focus
   on the server. Local outbox payload is dropped after acknowledgement.
3. **Notes must not disappear** — completion always writes the client’s notes
   fields when the lesson was still `scheduled`. If the lesson was already
   completed with different notes, V1 keeps the **server** notes and surfaces
   “Already completed” context via the refreshed lesson payload (client should
   show server values after sync). Future versions may append a preserved local
   copy into instructor notes with a clear marker if dual authorship appears.
4. **No collaborative OT** — no Google-Docs-style merging.

## Logout & privacy

- Local stores are scoped `u{userId}-o{organisationId}`.
- Logout attempts sync first.
- If unsynced mutations remain, logout is **blocked** until sync succeeds (so
  notes are not destroyed for privacy cleanup).
- After a clean sync, organisation operational IndexedDB data is cleared.
- Shared devices: always sign out when finished; offline cache is device-local.

## Session expiry while offline

Teaching continues from cache. Sync pauses with a calm “Sign in again to sync
saved changes” chip. Outbox is retained until a successful authenticated flush.

## Network-dependent features

Maps / travel / autocomplete degrade with clear copy (e.g. open-in-maps simply
unavailable offline). They must not block complete-lesson.

## First load

Offline cannot invent data never received. Opening Today while online
automatically prefetches the working day — no “Download for offline” button.
