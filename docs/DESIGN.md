# OwnLane — Style Reference

> Sunlit maker's notebook, with OwnLane green as the single chromatic accent.

**Theme:** light

OwnLane uses a Zapier-like warm cream notebook mood: cream canvas (`#fffefb`,
`#f8f4f0`), warm near-black text (`#201515`, `#36342e`), paper-thin hairline
borders, and pill controls at 20px radius. The orange accent is replaced by
**OwnLane Green** (`#168B55`) — used sparingly for primary buttons, active
chips, and small highlights. Typography pairs Inter for UI with Bricolage
Grotesque for display headings. Surfaces stay flat: almost no elevation,
generous 4px-rhythm spacing.

## Tokens — Colors

| Name | Value | Token | Role |
|------|-------|-------|------|
| OwnLane Green | `#168B55` | `--color-ownlane-green` | Primary accent (Zapier orange role) |
| Jelly Green | `#1a9d61` | `--color-jelly-green` | Hover / brighter green |
| Cream Paper | `#fffefb` | `--color-paper-white` | Canvas, cards, inputs |
| Parchment | `#f8f4f0` | `--color-parchment` / `--color-frost-green` | Soft wash, sidebar |
| Stone Linen | `#eceae3` | `--color-border` | Hairline borders |
| Driftwood | `#c5c0b1` | `--color-driftwood` | Input borders, muted edges |
| Ash Mist | `#b7b6b3` | `--color-ash-mist` | Placeholders |
| Ink Brown | `#201515` | `--color-ink-black` | Primary text |
| Bark | `#36342e` | `--color-bark` | Secondary text |
| Coffee Stone | `#413735` | `--color-coffee-stone` | Labels / tertiary |
| Stone Gray | `#939084` | `--color-muted` | Helper / meta |

## Tokens — Typography

### Inter — UI and body · `--font-haas-grot-text`
Weights 400 / 500 / 600. Nav, buttons, forms, body, captions.

### Bricolage Grotesque — Display · `--font-haas-grot-disp`
Page titles and marketing headlines. Slightly open letter-spacing.

### JetBrains Mono — Rare mono · `--font-martian-mono`
Sparse labels only.

## Tokens — Spacing & Shapes

**Base unit:** 4px

| Element | Radius |
|---------|--------|
| Tags | 4px |
| Cards / panels | 8px |
| Inputs | 4px |
| Buttons / chips | 20px |

**Layout:** page max 1200px · card padding 24px · section gap ~32px

## Surfaces

| Level | Value | Purpose |
|-------|-------|---------|
| Canvas | `#fffefb` | Page background |
| Wash | `#f8f4f0` | Sidebar, soft bands |
| Border | `#eceae3` | Hairlines |
| Inset | `#c5c0b1` | Stronger edges |

Shadows are almost unused. Prefer hairline borders.

## Components

### Primary button
OwnLane Green fill, cream text, 20px radius, no shadow. Hover → jelly green.

### Ghost / outlined button
Cream fill, 1px ink border, ink text, 20px radius.

### Text input
Cream fill, 1px driftwood border, 4px radius, 16px padding, min-height 56px.
Focus: ink border only — no coloured glow ring.

### Eyebrow
12px Inter semibold, uppercase, OwnLane Green, tracking 0.042em.

### Chip (selected)
OwnLane Green fill, cream text, 20px radius.

## Do's

- Cream / parchment backgrounds — not stark white as the default mood
- OwnLane Green as punctuation only (under ~5% of a layout)
- Warm brown-gray neutrals for text hierarchy
- Hairline borders instead of chunky offset shadows

## Don'ts

- Don't wash large areas in green
- Don't use cool slate blues (`#64748b`, `#94a3b8`) for muted text
- Don't restore SuperHi candy shadows as the default button style
- Don't use violet / orange as UI accents

## Live tokens

Source of truth in code: `apps/web/app/assets/css/tokens.css`
