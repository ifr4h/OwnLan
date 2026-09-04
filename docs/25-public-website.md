# Public website 2.0

Status: **implemented** (extends public profiles from phase 2)

## Product intent

Each instructor gets one opinionated public page at:

`/instructors/{slug}`

This is their website — not a page builder, not a second CMS. The same organisation profile data powers:

- the public site
- the profile editor (`/settings/profile`)
- the enquiry pipeline (`/pupils/enquiries`)

## URL and future custom domains

**Current canonical URL:** `https://ownlane.co.uk/instructors/{slug}`

**Future custom domain (not implemented):** DNS for `aminadriving.co.uk` would CNAME to OwnLane; routing layer maps host → `organisation_id` + slug, with canonical URL strategy revisited at that point (likely instructor domain becomes canonical when paid plan allows).

**Future subdomain (not implemented):** `amina.ownlane.co.uk` — same mapping idea; no architecture change required now.

## Public page structure

Single responsive page with anchor navigation:

1. Hero (name, headline, acquisition status, from-price, CTA)
2. Lessons & prices (structured services)
3. About (intro, teaching styles, vehicle)
4. Areas
5. FAQ
6. Contact / enquiry (sticky on mobile)

## Data model (shared with profile)

All on `organisations` unless noted:

| Field | Purpose |
|-------|---------|
| `profile_business_name` | Optional trading name |
| `profile_accent_colour` | Safe accent (`#RRGGBB`) |
| `profile_cover_path` | Optional hero image |
| `profile_teaching_styles` | JSON array of style keys |
| `profile_services` | JSON structured public offerings |
| `profile_faqs` | JSON Q&A (plain text) |
| `profile_social_links` | Validated Instagram/Facebook/TikTok/YouTube URLs |
| `profile_contact_*` | Optional phone/email/WhatsApp |
| `profile_show_phone/email` | Opt-in display |
| `profile_dual_controls` | Public vehicle note |
| `profile_allow_indexing` | SEO robots control |

`enquiries.service_interest` — optional string when learner clicks “Ask about this” on a service.

`profile_analytics_events` — first-party aggregate events (no PII).

## Public pricing rule

- Only `profile_services` / `profile_public_pricing` are exposed.
- Never pupil-specific rates, packages, or lesson charges.
- `profile_services` syncs legacy `profile_public_pricing` for backward compatibility.

## Service shape

```json
{
  "id": "mock-test",
  "name": "Mock test",
  "description": "A structured practice test followed by a lesson review.",
  "duration_minutes": 90,
  "price_pence": 6300,
  "type": "mock_test",
  "public": true
}
```

Types: `lesson`, `mock_test`, `refresher`, `motorway`, `test_day`.

## Acquisition CTA

| Mode | CTA |
|------|-----|
| open | Ask about lessons |
| limited | Ask about availability |
| waiting_list | Join waiting list |
| closed | No CTA (waiting list if allowed) |

## Enquiry integration

Website enquiries use the existing `Enquiry` domain — no `WebsiteContactForm`.

`POST /api/public/instructors/{slug}/enquire` accepts `service_interest`.

Flow: public site → enquiry → fit context → convert → pupil → book.

## Analytics

`POST /api/public/instructors/{slug}/track`

Events: `view`, `enquiry_started`, `enquiry_submitted`, `service_click`

**Never tracked:** name, phone, email, postcode, message.

Instructor summary in `GET /settings/profile` → `analytics`:

- visits this month
- enquiries submitted
- pupils added from enquiries
- enquiries by source tag (when known)

## SEO

Assembled from factual fields:

- Title: `{name} | {transmission} driving lessons in {area}`
- Meta description from intro, areas, from-price
- Canonical: share URL
- `noindex` when draft/unpublished or `profile_allow_indexing = false`
- JSON-LD: `Person`, `LocalBusiness`, `Service` — **no** `aggregateRating` or fake reviews
- Open Graph title/description/image

## Security

- `PublicContentSanitizer` — plain text only for intro/FAQ; script/style stripped
- Social URLs validated (scheme + host allow-list)
- Images via `ProfilePhotoStorage` (type/size validated)
- Draft/unpublished profiles return 404 on public API

## API routes

| Method | Path |
|--------|------|
| GET | `/public/instructors/{slug}` |
| GET | `/public/instructors/{slug}/photo` |
| GET | `/public/instructors/{slug}/cover` |
| POST | `/public/instructors/{slug}/enquire` |
| POST | `/public/instructors/{slug}/track` |
| GET/PUT | `/settings/profile` |
| POST | `/settings/profile/publish` |
| GET | `/settings/profile/preview` |
| POST | `/settings/profile/cover` |

## Demo

Amina Yusuf: `/instructors/amina-yusuf` — automatic, Milton Keynes, services incl. mock test, FAQs, enquiry from Sarah Ahmed in seed data.

## Deliberately deferred

- Custom domain provisioning / DNS
- Testimonials / reviews platform
- Learner photo gallery
- Drag-and-drop builder, custom CSS, themes
- Stripe / online pay on public site
- Area-specific SEO landing pages
- Dynamic OG image generation

## Tests

`PublicProfileEnquiryTest`, `PublicWebsiteTest` (Unit suite).
