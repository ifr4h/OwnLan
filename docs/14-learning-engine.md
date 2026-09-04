# Learning Engine — architecture (MVP)

## Principle

Reuse lessons, skills, routes, portal auth, and offline outbox. Add shared domain
tables; do not create microservices or paid teaching/AI SDKs.

## Domain

| Concept | Role |
|---------|------|
| `TeachingResource` | Reusable master (board / annotated map / article) |
| `LessonResource` | Lesson-specific snapshot or attachment (never overwrites master) |
| `RouteMoment` | Explicit in-drive or post-drive marker on a `LessonRoute` |
| `PrivatePracticeSession` | Learner self-report — **not** instructor assessment |
| `LearningContent` | Published OwnLane library item (structured content blocks JSON) |

## Teaching Studio

- Purpose-built Canvas 2D + pointer events (finger + Apple Pencil)
- Templates as JSON geometry packs (roundabout, T-junction, …)
- Scene JSON: objects, strokes, steps
- Modes on real-road boards: MOVE MAP | DRAW (Leaflet underlay + overlay canvas)

## Drive recording

- Existing `LessonRoute` + local buffer → stop upload
- Extend with `RouteMoment` (mark requires one tap; enrich when parked)
- Offline: moments queued with route outbox (IndexedDB)

## Learner surfaces

- `/portal/learn` — deterministic personalisation from next_focus, lesson skills, shared resources, moments
- Routes / Recap / Progress — wire moments + lesson resources into existing pages
- Private practice — separate API; instructor sees concise “since last lesson” only

## Cost

Leaflet + OSM, Chart.js, OwnLane canvas — **£0** recurring beyond hosting.
