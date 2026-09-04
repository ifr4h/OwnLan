# Learning Engine — costs, privacy, device limits

## Dependencies / cost report

| Name | Purpose | OSS? | License | Where | Recurring | Usage-based | Beta cost | Scaling | Alternative considered |
|------|---------|------|---------|-------|-----------|-------------|-----------|---------|------------------------|
| Leaflet | Maps (routes, real-road, replay) | Yes | BSD-2 | Client | £0 | £0 | £0 | Tile fair-use | Mapbox (£ usage) |
| OSM tiles | Basemap | Community | ODbL / tile ToS | Client CDN | £0 | Fair use | £0 | Self-host tiles later | Mapbox, Google |
| Chart.js + vue-chartjs | Learner charts | Yes | MIT | Client | £0 | £0 | £0 | Bundle size | Custom SVG |
| OwnLane Canvas 2D | Teaching Studio | Own code | — | Client | £0 | £0 | £0 | — | Paid whiteboard SDKs (rejected) |
| Encoded polyline | Route storage | Own code | — | Server+client | £0 | £0 | £0 | — | PostGIS (deferred) |
| PostgreSQL JSON | Scene / content blocks | Existing | — | Server | Existing hosting | — | — | — | Separate CMS SaaS (rejected) |

**New recurring software cost for Teaching Studio / Learn / routes: £0 beyond OwnLane hosting.**

No paid AI, whiteboard SDK, chart SaaS, or route-processing service introduced.

### Flagship expansion (Interactive Learn / Playback / Companion)

| Addition | Cost |
|----------|------|
| ScenarioPlayer (OwnLane canvas + existing templates) | £0 |
| Lesson Playback (Leaflet + own polyline interpolate) | £0 |
| Companion accounts (PostgreSQL + session cookie) | £0 |
| Temporary shares (token table) | £0 |
| Learning activities table | £0 |

Still **no** paid AI tutor, LMS, quiz SaaS, companion platform, or replay SaaS.


## PWA / GPS limitations

- iOS Safari / installed PWA: background geolocation is unreliable or unavailable. Recording should keep OwnLane **foregrounded**.
- Android Chrome PWA: better, still not guaranteed when screen locked.
- Domain model (`LessonRoute`, `RouteMoment`) is native-app ready without schema rewrite.
- Instructors are told recording requires the app to stay open (UI + docs).

## Safety

- Mark moment = one tap (passenger / when parked).
- Annotation and Teaching Studio = before drive, parked, or after lesson only.
- Private practice logging = after practice, not while driving.
