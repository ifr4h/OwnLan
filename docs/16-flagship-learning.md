# Flagship Learning Engine — architecture & delivery report

## Architecture (chosen)

| Feature | Approach |
|---------|----------|
| Interactive Road Knowledge | `LearningContent` scenario blocks + shared `utils/teaching/*` + `ScenarioPlayer` |
| Lesson Playback | `PlaybackService` aggregates `LessonRoute` + `RouteMoment` + `LessonResource` + skills |
| LessonMoment | API view over `RouteMoment` (no parallel event store) |
| Learning activity | `learning_activities` — never writes `LearnerSkillProgress` |
| Private practice | Existing sessions + companion note fields; separate from instructor lessons |
| Companion | **DEFERRED** — `companion_accounts` + `learner_companions` preserved; HTTP/UI gated. See `17-companion-access-decision.md` |
| Temporary share | `temporary_shares` signed token, expiry, revoke — not Companion |

Provenance labels: instructor | learner | companion | system.

---

## Final report

### 1. Interactive Road Knowledge architecture
Learner home `/portal/learn` personalises via deterministic rules (next focus → instructor resources → last-lesson moments → quick review → explore library). Content lives in `learning_contents` with OwnLane-authored interactive blocks. Activity posts to `learning_activities` only.

### 2. Scenario engine
Shared Teaching Studio templates (`getTemplatePack` / `drawRoadShapes`) power `ScenarioPlayer` (learner) and Teaching Studio (instructor). Primitives: road templates, vehicles, lights, signs, steps, selectable options. No second rendering engine.

### 3. Personalisation rules
Priority: instructor `next_focus` → instructor-shared lesson resources → route moments → quick interactive review → category explore. Interactive slugs preferred when matching focus. No paid AI.

### 4. Interactive resources implemented
- `interactive-third-exit` (roundabout lane/path)
- `interactive-give-way`
- `interactive-traffic-lights`
- `interactive-meeting`
Plus existing non-interactive library content.

### 5. Lesson Playback architecture
`GET /portal/lessons/{id}/playback` → route polyline, moments, chapters, resources with scenes, skills. UI: `/portal/lessons/[id]/playback` with map + scrub timeline + moment sheet. Not video/audio.

### 6. LessonMoment implementation
`RouteMoment` is the store. Playback exposes type `INSTRUCTOR_MARKER` with optional linked `LessonResource` explanation. Mark action remains instructor route recording (`POST lessons/{id}/moments`).

### 7. Map/timeline synchronisation
`PortalPlaybackMap` + playhead fraction interpolates car along polyline; tap marker selects moment; scrub updates playhead; chapters jump offset.

### 8. Playback → Teaching Studio
Moments with attached `LessonResource` include full `scene` JSON; `SceneViewer` renders read-only Teaching Studio snapshot.

### 9. Playback → Progress
Skills practised listed with instructor provenance. GPS does not imply competence.

### 10. Playback → Learn
`related_learn_slugs` on moments link into interactive content (e.g. third-exit).

### 11. Private Practice architecture
Separate `private_practice_sessions` entity. Quick log via portal. Instructor “Since last lesson” summary on pupil page. Never mutates instructor ratings.

### 12. Recorded private practice
Route infrastructure reusable (`lesson_route_id` column). Full learner GPS start/stop UX deferred to keep scope lean; logging + companion notes shipped.

### 13. Learner reflection provenance
Sessions serialize `feeling` / `note` as `learner_self_report`; `companion_note` as `companion`. Displayed separately.

### 14. Companion architecture (deferred)
`CompanionAccount` identity ↔ `LearnerCompanion` link with permissions JSON. Separate session component `companionUser`. **Not exposed in beta** — see `17-companion-access-decision.md`.

### 15. Companion identity/authentication (deferred)
Invite token → activate password → login cookie `_companion_identity`. Unified sign-in is instructor + learner only.

### 16. Companion permission model (future)
Broad permission scopes exist in schema (`lessons`, `money`, `progress`, etc.). Future **Payment contact** will use a narrow subset enforced server-side — not hidden nav items.

### 17. Payment contact (future first use case)
Money permission prototype returns credit + amount due only. Full Payment contact product not shipped.

### 18. Practice companion (future, evidence-gated)
Practice permission prototype exists. Prefer temporary scoped sharing first. Persistent Practice companion only if usage warrants it.

### 19. Temporary sharing model
`TemporaryShareService` — high-entropy token, 7-day expiry, revoke, minimal payload (no full polyline/home addresses). `GET /share/{token}`.

### 20. Companion security tests
`CompanionServiceTest`: payment-only isolation, practice-only isolation, revoke, cross-learner forbid, activity ≠ progress, companion note provenance. Service-layer tests active; HTTP endpoints gated by `CompanionFeature`.

### 21. Instructor continuity integration
`GET learners/{id}/private-practice` → drives, duration, skills, companion note count, provenance disclaimer on pupil page.

### 22. Learner Journey visualisations
`PlaybackService::journeyExpanded` adds private practice hours + learning activity count with provenance map. Portal journey consumes expanded payload.

### 23. PWA/offline behaviour
Existing portal offline home cache retained. Private practice recording outbox deferred; schema supports route link. Activity/logging require network today.

### 24. Accessibility
Large tap targets, labelled controls, reduced-motion map/scenario behaviour, non-colour-only scenario feedback, map alt text.

### 25. Localisation preparation
Portal strings in `locales/en-GB/portal.ts`; companion uses plain English (ready to extract). No translation pass.

### 26. External libraries added
**None.** Leaflet/OSM, Chart.js, OwnLane canvas already present. £0 incremental SaaS.

### 27. Recurring/usage-based costs introduced
**£0.** No paid AI, LMS, whiteboard SDK, or replay SaaS.

### 28. Performance impact
Playback/maps/scenario canvas ClientOnly + lazy. Home does not load historical geometry. Geometry loaded per-lesson on playback.

### 29. Automated tests
- `CompanionServiceTest` (6)
- `LearningEngineServiceTest` (3) updated truncate list
- Existing portal/learning tests retained

### 30. Platform limitations
- No SMS/email delivery for companion invites (path returned for copy)
- Companion booking actions not enabled
- Learner GPS private-practice recording UI not finished (schema ready)
- Temporary share is metadata-light by design

### 31. Deliberately deferred
Broad Companion product (UI + HTTP), messaging, camera/audio, dashcam, live nav, leaderboards, pass %, AI assessment, paid quiz SaaS, booking-on-behalf, WhatsApp. See `17-companion-access-decision.md`.

### 32. Remaining blockers before beta
- QA full demo walkthrough on device matrix (390–iPad–desktop)
- Email/SMS invite delivery (or clear copy-link UX polish)
- Confirm retention/deletion policy UI for routes
- Optional: finish learner practice drive recorder using existing LessonRoute pipeline

---

## Demo credentials

| Role | Login | Password |
|------|-------|----------|
| Instructor | `instructor` | `instructor` |
| Learner (Sarah) | `learner` | `learner` |

Companion demo accounts exist in the seed data but the broad Companion product is deferred for beta. See `17-companion-access-decision.md`.

Paths: `/portal/login` · Replay from lesson recap · Learn `/portal/learn`
