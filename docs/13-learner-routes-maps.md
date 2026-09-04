# Mapping & route recording — cost and privacy notes

## Provider choice (MVP)

OwnLane uses **Leaflet + OpenStreetMap** raster tiles for learner route maps.

| Option | Visual quality | Cost at early stage | Notes |
|--------|----------------|---------------------|-------|
| **Leaflet + OSM tiles (chosen)** | Good | **£0** usage billing | Attribution required; fair-use tile usage; style limited vs Mapbox |
| Mapbox GL | Excellent | Usage-based after free tier | Strong styling; recurring cost scales with MAU/map loads |
| Google Maps JS | Excellent | Usage-based | Heavier ToS/privacy review; cost at scale |

**Decision:** Prefer OSM for MVP. Revisit Mapbox only if map polish becomes a conversion bottleneck and volume stays predictable.

### Expected external costs

- **Tile requests:** £0 with OSM public tiles for low demo/design-partner volume.
- **Do not** download offline map datasets into the PWA.
- If tile abuse or rate limits appear, move to a self-hosted tile proxy or a paid CDN — budget separately.

## Route geometry

- Stored as **Google-encoded polyline** on `lesson_routes.encoded_polyline`.
- Approximate distance via haversine (not survey-grade).
- Soft retention target: **730 days** (ops purge job deferred).

## Privacy rules

- Instructor must **explicitly** start and stop recording.
- Never continuous background tracking outside an active recording session.
- Learner sees a route only after instructor **Share with learner**.
- Tenant-scoped; portal scoped to own learner.
- Soft-delete clears geometry and learner visibility.
