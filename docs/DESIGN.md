# SuperHi --- Style Reference

> Paper-cut shapes scattered across a pale-green classroom wall

**Theme:** light

SuperHi reads like a creative-school art room translated to screen: a
near-white pale-green canvas strewn with oversized flat paper-cut shapes
in candy-bright primaries, wrapped around quietly confident typography
that never competes with the playfulness. The palette is almost
monochrome in interface chrome --- deep ink text, white surfaces, one
fresh green that powers every action --- while the decorative layer is
intentionally loud: yellow pentagons, red half-circles, green triangles,
pink stars and powder-blue blobs float behind content like cutouts on a
studio wall. Components are pill-soft (24--48px radii), buttons are
chunky capsules with arrow glyphs, and the tone is friendly, confident,
and unmistakably creative.

## Tokens --- Colors

Inspired by the Tapdaa lime / forest / mint palette
(https://www.tapdaa.com/). Values below are the **exact** hex codes
measured from Tapdaa’s Framer tokens / CSS — not approximations.

  --------------------------------------------------------------------------------
  Name             Value               Token                      Role
  ---------------- ------------------- -------------------------- ----------------
  Lime             `#c1f48f`           `--color-ownlane-green`    Brand mark,
                                                                  chips, accent
                                                                  shadow

  Forest ink       `#1e2c0f`           `--color-ink-black`        Primary text
                                                                  rgb(30, 44, 15)

  Black            `#000000`           `--color-carbon`           Primary CTA fill
                                                                  (Tapdaa buttons)

  White            `#ffffff`           `--color-paper-white`      Cards / surfaces

  Canvas           `#f7faf4`           `--color-chalk-green`      Page background

  Mint wash        `#eef8e4`           `--color-frost-green`      Soft wash

  Border wash      `#eff2ed`           `--color-border`           Borders / sidebar

  Muted            `#777a74`           `--color-muted`            Secondary copy

  Olive            `#3e4734`           `--color-olive`            Secondary text

  Soft sage        `#a9df74`           `--color-soft-sage`        Deeper lime
                                                                  rgb(169, 223, 116)

  Lilac            `#e3a6ff`           `--color-lilac`            Price cards /
                                                                  featured surfaces
                                                                  rgb(227, 166, 255)

  Lilac deep       `#d399ed`           `--color-lilac-deep`       Secondary CTA /
                                                                  “Order now” style
                                                                  rgb(211, 153, 237)

  Teal             `#8ff4e2`           `--color-teal`             Decorative accent
                                                                  rgb(143, 244, 226)

  Near black       `#101820`           `--color-near-black`       Supporting dark

  Paper soft       `#fefffc`           `--color-paper-soft`       Soft white panels
  --------------------------------------------------------------------------------

## Tokens --- Typography

### Haas Grot Disp --- Display and headings only --- the geometric grotesque has a sturdy, slightly condensed skeleton that holds 92px hero lines without losing legibility; weight stays at 400 because the tight letter-spacing (-0.03em at the largest sizes) does the heavy-lifting, not bold weight · `--font-haas-grot-disp`

-   **Substitute:** Inter, Manrope, or Suisse Int'l
-   **Weights:** 400
-   **Sizes:** 24px, 35px, 42px, 52px, 62px, 72px, 92px
-   **Line height:** 1.00, 1.10, 1.15, 1.35
-   **Letter spacing:** -0.0300em at 92px down to -0.0040em at 24px
-   **OpenType features:** `"ss01" on, "tnum" on`
-   **Role:** Display and headings only --- the geometric grotesque has
    a sturdy, slightly condensed skeleton that holds 92px hero lines
    without losing legibility; weight stays at 400 because the tight
    letter-spacing (-0.03em at the largest sizes) does the
    heavy-lifting, not bold weight

### Haas Grot Text --- Body, navigation, buttons, and smaller UI text --- the text cut of the same family tuned for readability at small sizes with looser tracking than the display companion · `--font-haas-grot-text`

-   **Substitute:** Inter, Manrope, or Suisse Int'l
-   **Weights:** 400
-   **Sizes:** 16px, 20px, 22px, 24px
-   **Line height:** 1.10, 1.25, 1.35, 1.40
-   **Letter spacing:** -0.0200em at 16px down to -0.0070em at 24px
-   **Role:** Body, navigation, buttons, and smaller UI text --- the
    text cut of the same family tuned for readability at small sizes
    with looser tracking than the display companion

### Martian Mono --- Promotional ticker text, tag labels, and code-flavored micro-copy --- a monospaced voice used sparingly for editorial texture, not for entire paragraphs · `--font-martian-mono`

-   **Substitute:** JetBrains Mono, IBM Plex Mono, or Space Mono
-   **Weights:** 400
-   **Sizes:** 12px, 17px
-   **Line height:** 1.00, 1.30, 1.35
-   **Letter spacing:** -0.0130em
-   **Role:** Promotional ticker text, tag labels, and code-flavored
    micro-copy --- a monospaced voice used sparingly for editorial
    texture, not for entire paragraphs

### Type Scale

  Role           Size   Line Height   Letter Spacing   Token
  -------------- ------ ------------- ---------------- -----------------------
  caption-mono   12px   1.35          -0.156px         `--text-caption-mono`
  body-sm        16px   1.4           -0.32px          `--text-body-sm`
  body           20px   1.4           -0.2px           `--text-body`
  subheading     22px   1.35          -0.176px         `--text-subheading`
  heading-sm     24px   1.25          -0.168px         `--text-heading-sm`
  heading-lg     42px   1.1           -0.378px         `--text-heading-lg`
  display        52px   1.1           -0.468px         `--text-display`
  display-lg     62px   1.05          -0.558px         `--text-display-lg`
  display-xl     72px   1             -0.648px         `--text-display-xl`
  mega           92px   1             -2.76px          `--text-mega`

## Tokens --- Spacing & Shapes

**Base unit:** 4px

**Density:** comfortable

### Spacing Scale

  Name   Value   Token
  ------ ------- ----------------
  4      4px     `--spacing-4`
  8      8px     `--spacing-8`
  12     12px    `--spacing-12`
  16     16px    `--spacing-16`
  20     20px    `--spacing-20`
  24     24px    `--spacing-24`
  28     28px    `--spacing-28`
  32     32px    `--spacing-32`
  40     40px    `--spacing-40`
  48     48px    `--spacing-48`

### Border Radius

  Element   Value
  --------- --------
  tags      5000px
  cards     24px
  small     16px
  inputs    5000px
  shapes    0px
  buttons   48px

### Layout

-   **Page max-width:** 1200px
-   **Section gap:** 80px
-   **Card padding:** 24px
-   **Element gap:** 16px

## Components

### Primary Pill Button

**Role:** Main call-to-action --- course enrollment, catalog navigation,
see-more links

Fill #168B55, text #ffffff in Haas Grot Text 20px weight 400,
letter-spacing -0.01em, 12px 24px padding, border-radius 48px (full
pill), includes a trailing arrow glyph (→) after the label. Hard offset
shadow 0 4px 0 0 #111118 gives a sticker-like pop without softness.

### Dark Pill Button

**Role:** Secondary action --- free trials, sign-in entry points

Fill #000000, text #ffffff, same 48px pill radius and 20px label, 12px
24px padding, no arrow. Hard offset shadow 0 4px 0 0 #168B55 echoes the
primary brand color as the shadow hue.

### Ghost Text Button

**Role:** Tertiary navigation --- 'About', 'Catalog' nav links, footer
links

No fill, no border, text #111118 in Haas Grot Text 20px weight 400, 8px
vertical padding, transparent background, underline only on hover.

### Announcement Ticker

**Role:** Site-wide promotional bar at the very top of every page

Full-bleed bar, fill #111118, text #ffffff in Martian Mono 12px,
letter-spacing -0.013em, uppercase tracking. Repeating message with a
bullet separator. Fades to transparent on the left edge via the
green-to-clear linear-gradient, suggesting an infinite marquee.

### Top Navigation Bar

**Role:** Primary site navigation, sticky below the ticker

White background #ffffff, 1px bottom border #E2F0E7, height \~64px.
Left: round 32px logo mark + 'Catalog' and 'About' links in Haas Grot
Text 20px. Right: search icon, 'Cart' link, 'Sign in' pill button. No
drop shadow --- the 1px hairline is the only separator.

### Hero Text Block

**Role:** Centered headline-and-CTA composition on the home hero

Headline in Haas Grot Disp 72px weight 400, color #111118,
letter-spacing -0.03em, two lines maximum, centered. Above the headline,
a small 48px round icon mark in #168B55 (the smiley emoji container).
Below: subhead in Haas Grot Text 20px #111118, then two pill buttons
side by side with 16px gap.

### Decorative Shape Layer

**Role:** Atmospheric cutout shapes scattered behind page content

Absolute-positioned oversized flat geometric forms --- pentagons,
triangles, semicircles, stars, circles --- in the five accent colors.
Sizes range 200--600px, rotated at varied angles, with 0px radius and no
shadow. Sits behind the content layer at z-index 0, partially clipped by
the viewport edges to create the impression of shapes spilling off the
page.

### Course Card

**Role:** Catalog grid item --- links to individual course pages

White surface #ffffff, 24px border-radius, 1px border #E2F0E7, 24px
padding. Course image at top with 16px top radius. Title in Haas Grot
Text 20px weight 400, meta line below in Martian Mono 12px, price or
'Free' tag at bottom. Hard offset shadow 0 2px 0 0 #111118.

### Student Work Card

**Role:** Showcase grid item --- displays student project thumbnails

White surface, 16px border-radius, no shadow, image fills the entire
card edge-to-edge with the title overlaid at the bottom-left in a small
text bar. Designed to be visual-first, with minimal chrome.

### Featured Section Wash

**Role:** Highlighted content band (e.g. 'Most popular')

Full-bleed background in #ffbac4 (bubblegum) or #ffda00 (hi-yellow),
content sits inside a 1200px max-width container. Section heading in
Haas Grot Disp 42px #111118 left-aligned, with a right-aligned 'See all
content →' primary pill button at the section's vertical center.

### Input Field

**Role:** Email capture, search, newsletter signup

Fill #F3F8F4, border 1px solid #E2F0E7, border-radius 5000px (full
pill), padding 12px 20px, placeholder text in #111118 at 40% opacity,
Haas Grot Text 16px. Focus state adds a 2px #168B55 outer ring with 2px
offset.

### Tag / Category Pill

**Role:** Course category labels, filter chips

5000px border-radius, fill #E2F0E7, text #111118 in Haas Grot Text 16px,
padding 4px 12px, no border. Active filter state swaps fill to #168B55
and text to #ffffff.

### Cookie Consent Bar

**Role:** GDPR notice pinned to the bottom of the viewport

Fixed full-width, fill #111118, padding 16px 24px, text #ffffff in Haas
Grot Text 16px left-aligned with body copy and a 'We use essential
cookies...' message. Right side: two pill buttons --- 'Essential cookies
only' in ghost style (white text, 1px white border) and 'Sure thing' as
a dark-fill pill with white text.

### Round Icon Container

**Role:** Brand mark, category icon, avatar fallback

48px circle, fill #168B55, contains a centered emoji or icon glyph in
#ffffff. Used as the small smiley mark above the hero headline and as a
floating category indicator.

## Do's and Don'ts

### Do

-   Use #168B55 as the single chromatic for every filled action button,
    active link, and brand-tinted icon container --- no secondary brand
    colors on buttons.
-   Set border-radius to 48px for all primary and secondary buttons;
    24px for cards; 5000px for inputs, tags, and icon containers.
-   Weight 400 across all three type families --- the hierarchy comes
    from size, line-height, and letter-spacing, not from going bold.
-   Anchor letter-spacing to -0.03em at 92px and ease it to -0.004em at
    24px so display type tightens as it scales up.
-   Scatter at least 3--5 oversized flat geometric shapes in the five
    accent colors behind any full-bleed hero or section, with 0px radius
    and no shadow.
-   Use Martian Mono 12px only for promotional ticker text, tag labels,
    and code-flavored meta lines --- never for paragraphs.
-   Build cards on #ffffff with a 1px #E2F0E7 hairline border and a hard
    0 2px 0 0 #111118 offset shadow --- no soft drop shadows.

### Don't

-   Don't introduce a second brand chromatic on buttons or links ---
    #168B55 owns every action; the five accent colors are decoration
    only.
-   Don't use bold or semibold weights --- the type system runs entirely
    on 400, and bumping weight breaks the quiet, confident voice.
-   Don't apply soft blurred drop shadows (rgba 0,0,0,0.x with blur \>
    4px) --- the system uses hard offset shadows or no shadow at all.
-   Don't use gradients inside decorative shapes or content blocks ---
    only the announcement ticker carries a gradient, and only as a
    fade-to-clear.
-   Don't put text directly on the pale-green canvas without a card or
    button surface --- the contrast ratio of #111118 on #F3F8F4 is fine,
    but layout rhythm requires cards to create the page grid.
-   Don't use the accent colors (red, yellow, green, pink, sky) on text
    or borders --- they live exclusively as fills of the decorative
    shape layer.
-   Don't use border-radius below 16px on any visible component ---
    sharp corners would break the soft, pill-driven visual language.

## Surfaces

  -------------------------------------------------------------------------
  Level             Name           Value             Purpose
  ----------------- -------------- ----------------- ----------------------
  0                 Chalk Green    `#F3F8F4`         Page background ---
                    Canvas                           the default stage for
                                                     the scattered cutout
                                                     shapes

  1                 Paper White    `#ffffff`         Card surfaces,
                    Card                             elevated content
                                                     blocks, button fills

  2                 Frost Green    `#E2F0E7`         Soft section
                    Wash                             background and input
                                                     field rest state

  3                 Bubblegum      `#ffbac4`         Featured content
                    Section                          section wash
                                                     (e.g. 'Most popular')

  4                 Hi-Yellow      `#ffda00`         Promotional section
                    Section                          wash for
                                                     high-attention blocks
  -------------------------------------------------------------------------

## Elevation

-   **Primary CTA button:** `0 4px 0 0 #111118`
-   **Secondary dark button:** `0 4px 0 0 #168B55`
-   **Course card:** `0 2px 0 0 #111118`

## Imagery

Imagery is split into two distinct registers. The decorative layer is
oversized flat paper-cut geometric shapes --- pentagons, triangles,
semicircles, stars, and circles in marker-red, hi-yellow, jelly-green,
soft-sage, and bubblegum-pink --- scattered behind content at
full-bleed, overlapping the canvas edges, with sharp corners and zero
shadow. The content layer is student project cards and course thumbnails
shown as tight rectangular crops with 16px radii, no rounded masking,
sitting on white card surfaces. Photography (when used) is high-key and
product-focused, not lifestyle. There are no gradients in the decorative
shapes; the only gradient is a violet-to-transparent left fade on the
announcement bar.

## Layout

Layout is full-bleed with a 1200px content rail. The announcement ticker
spans edge to edge at the very top, followed by a sticky white nav bar
with the wordmark on the left and account actions on the right. The hero
is a centered text block --- single headline, subhead, and two pill
buttons side by side --- with decorative shapes floating in the negative
space around the text rather than in a split column. Below the hero,
sections alternate between Chalk Green canvas and white card surfaces,
stacking vertically with 80px gaps. Course and student work cards use a
3-column grid that collapses to 2 then 1. Section headers are
left-aligned with a right-aligned 'See all' link. The cookie consent is
a fixed full-width bar at the bottom with a dark fill and two action
buttons aligned right.

## Agent Prompt Guide

Quick Color Reference: - text: #111118 - background: #F3F8F4 (canvas) /
#ffffff (cards) - border: #E2F0E7 - accent shapes: #ffda00, #ff4141,
#ffbac4, #A8D5B5, #16ab59 - primary action: no distinct CTA color

Example Component Prompts:

1.  Hero text block: center a 48px #168B55 round icon container at the
    top, then a two-line headline in Haas Grot Disp 72px weight 400,
    #111118, letter-spacing -2.16px, line-height 1.0. Subhead in Haas
    Grot Text 20px #111118, line-height 1.4. Below, two pill buttons
    with 16px gap: a primary 48px-pill #168B55 button with white 20px
    label and trailing → arrow, 12px 24px padding, hard shadow 0 4px 0 0
    #111118; beside it a secondary 48px-pill #000000 button with white
    label, same padding, shadow 0 4px 0 0 #168B55.

2.  Course card: #ffffff surface, 24px radius, 1px #E2F0E7 border, 24px
    padding, hard shadow 0 2px 0 0 #111118. Course image at top spanning
    the card width with 16px top radius. Title in Haas Grot Text 20px
    #111118, meta in Martian Mono 12px #111118 at 60% opacity, and a
    5000px-radius tag pill in #E2F0E7 with #111118 16px label for the
    category.

3.  Announcement ticker bar: full-bleed #111118 strip, 32px tall, text
    in Martian Mono 12px #ffffff, letter-spacing -0.156px, repeating
    message with • separator. Apply a linear-gradient(to left, #168B55
    0%, rgba(0,0,0,0) 50%) as a left-edge mask fade.

No distinct primary action color was observed; use the extracted neutral
button treatments instead of inventing a filled CTA color.

5.  Cookie consent bar: fixed to bottom, full-width, fill #111118, 16px
    24px padding. Left-aligned body copy in Haas Grot Text 16px #ffffff.
    Right-aligned two buttons with 8px gap: a ghost button (transparent
    fill, 1px #ffffff border, white text, 48px radius) reading
    'Essential cookies only', and a dark pill (#000000 fill, white text,
    48px radius) reading 'Sure thing'.

## Decorative Shape System

The scattered paper-cut shape layer is the brand's most distinctive
visual signature --- treat it as a first-class component. Build a
reusable shape library with five primitives: pentagon, triangle,
semicircle, star, and circle. Each instance picks one of the five accent
colors (#ffda00, #ff4141, #ffbac4, #A8D5B5, #16ab59) as its fill, uses
0px border-radius, zero shadow, and is placed at absolute position
behind the content layer with a random rotation between -15° and +15°.
Sizes range from 200px to 600px. Shapes should always be clipped by the
viewport edge --- they spill off the page, they don't sit fully inside
it. On any full-bleed section, include 3--5 shapes minimum; on the home
hero, include 6--8.

## Elevation Philosophy

Elevation in this system is not about soft Gaussian shadows --- it's
about hard, offset, sticker-like pop. Every shadow is a 0px-blur offset
shadow (e.g. 0 4px 0 0 #111118), creating the impression that cards and
buttons are physically stuck onto the page rather than floating above
it. This pairs naturally with the paper-cut decorative layer: both the
content cards and the background shapes are flat cutouts, and the offset
shadow is the only thing telling you which layer is on top.

## Similar Brands

-   **Skillshare** --- Same creative-education positioning with a
    bright, illustrative decorative layer behind content and pill-shaped
    CTA buttons
-   **Canva's design school pages** --- Same
    single-vibrant-accent-plus-pastel-canvas treatment and oversized
    flat geometric shapes used as atmospheric background
-   **Maven** --- Same soft-green canvas, confident centered hero
    typography, and pill-radius buttons with trailing arrow glyphs
-   **Glitch** --- Same playful, creative-platform tone with a pale
    pastel background and one saturated brand color doing all the
    action-button work
-   **Are.na** --- Same clean, near-white canvas with quiet typography
    and the visual personality carried by scattered color accents rather
    than chrome

## Quick Start

### CSS Custom Properties

``` css
:root {
  /* Colors — exact Tapdaa values */
  --color-ownlane-green: #c1f48f;
  --color-ink-black: #1e2c0f;
  --color-paper-white: #ffffff;
  --color-chalk-green: #f7faf4;
  --color-frost-green: #eef8e4;
  --color-carbon: #000000;
  --color-olive: #3e4734;
  --color-muted: #777a74;
  --color-border: #eff2ed;
  --color-soft-sage: #a9df74;
  --color-lilac: #e3a6ff;
  --color-teal: #8ff4e2;
  --color-near-black: #101820;
  --color-paper-soft: #fefffc;

  /* Typography — Font Families */
  --font-haas-grot-disp: 'Haas Grot Disp', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --font-haas-grot-text: 'Haas Grot Text', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --font-martian-mono: 'Martian Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* Typography — Scale */
  --text-caption-mono: 12px;
  --leading-caption-mono: 1.35;
  --tracking-caption-mono: -0.156px;
  --text-body-sm: 16px;
  --leading-body-sm: 1.4;
  --tracking-body-sm: -0.32px;
  --text-body: 20px;
  --leading-body: 1.4;
  --tracking-body: -0.2px;
  --text-subheading: 22px;
  --leading-subheading: 1.35;
  --tracking-subheading: -0.176px;
  --text-heading-sm: 24px;
  --leading-heading-sm: 1.25;
  --tracking-heading-sm: -0.168px;
  --text-heading-lg: 42px;
  --leading-heading-lg: 1.1;
  --tracking-heading-lg: -0.378px;
  --text-display: 52px;
  --leading-display: 1.1;
  --tracking-display: -0.468px;
  --text-display-lg: 62px;
  --leading-display-lg: 1.05;
  --tracking-display-lg: -0.558px;
  --text-display-xl: 72px;
  --leading-display-xl: 1;
  --tracking-display-xl: -0.648px;
  --text-mega: 92px;
  --leading-mega: 1;
  --tracking-mega: -2.76px;

  /* Typography — Weights */
  --font-weight-regular: 400;

  /* Spacing */
  --spacing-unit: 4px;
  --spacing-4: 4px;
  --spacing-8: 8px;
  --spacing-12: 12px;
  --spacing-16: 16px;
  --spacing-20: 20px;
  --spacing-24: 24px;
  --spacing-28: 28px;
  --spacing-32: 32px;
  --spacing-40: 40px;
  --spacing-48: 48px;

  /* Layout */
  --page-max-width: 1200px;
  --section-gap: 80px;
  --card-padding: 24px;
  --element-gap: 16px;

  /* Border Radius */
  --radius-lg: 8px;
  --radius-2xl: 16px;
  --radius-3xl: 24px;
  --radius-3xl-2: 32px;
  --radius-full: 48px;
  --radius-full-2: 500px;
  --radius-full-3: 800px;
  --radius-full-4: 5000px;

  /* Named Radii */
  --radius-tags: 5000px;
  --radius-cards: 24px;
  --radius-small: 16px;
  --radius-inputs: 5000px;
  --radius-shapes: 0px;
  --radius-buttons: 48px;

  /* Surfaces */
  --surface-chalk-green-canvas: #f7faf4;
  --surface-paper-white-card: #ffffff;
  --surface-frost-green-wash: #eef8e4;
  --surface-bubblegum-section: #ffbac4;
  --surface-hi-yellow-section: #ffda00;
}
```

### Tailwind v4

``` css
@theme {
  /* Colors — exact Tapdaa values */
  --color-ownlane-green: #c1f48f;
  --color-ink-black: #1e2c0f;
  --color-paper-white: #ffffff;
  --color-chalk-green: #f7faf4;
  --color-frost-green: #eef8e4;
  --color-carbon: #000000;
  --color-olive: #3e4734;
  --color-muted: #777a74;
  --color-border: #eff2ed;
  --color-soft-sage: #a9df74;
  --color-lilac: #e3a6ff;
  --color-teal: #8ff4e2;
  --color-near-black: #101820;
  --color-paper-soft: #fefffc;

  /* Typography */
  --font-haas-grot-disp: 'Haas Grot Disp', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --font-haas-grot-text: 'Haas Grot Text', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --font-martian-mono: 'Martian Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* Typography — Scale */
  --text-caption-mono: 12px;
  --leading-caption-mono: 1.35;
  --tracking-caption-mono: -0.156px;
  --text-body-sm: 16px;
  --leading-body-sm: 1.4;
  --tracking-body-sm: -0.32px;
  --text-body: 20px;
  --leading-body: 1.4;
  --tracking-body: -0.2px;
  --text-subheading: 22px;
  --leading-subheading: 1.35;
  --tracking-subheading: -0.176px;
  --text-heading-sm: 24px;
  --leading-heading-sm: 1.25;
  --tracking-heading-sm: -0.168px;
  --text-heading-lg: 42px;
  --leading-heading-lg: 1.1;
  --tracking-heading-lg: -0.378px;
  --text-display: 52px;
  --leading-display: 1.1;
  --tracking-display: -0.468px;
  --text-display-lg: 62px;
  --leading-display-lg: 1.05;
  --tracking-display-lg: -0.558px;
  --text-display-xl: 72px;
  --leading-display-xl: 1;
  --tracking-display-xl: -0.648px;
  --text-mega: 92px;
  --leading-mega: 1;
  --tracking-mega: -2.76px;

  /* Spacing */
  --spacing-4: 4px;
  --spacing-8: 8px;
  --spacing-12: 12px;
  --spacing-16: 16px;
  --spacing-20: 20px;
  --spacing-24: 24px;
  --spacing-28: 28px;
  --spacing-32: 32px;
  --spacing-40: 40px;
  --spacing-48: 48px;

  /* Border Radius */
  --radius-lg: 8px;
  --radius-2xl: 16px;
  --radius-3xl: 24px;
  --radius-3xl-2: 32px;
  --radius-full: 48px;
  --radius-full-2: 500px;
  --radius-full-3: 800px;
  --radius-full-4: 5000px;
}
```

## Application UI (instructor product)

Marketing pages may use the decorative cutout layer and larger display sizes.
The instructor app stays calmer and denser while using the same brand colours.

### App layout

- Desktop (≥900px): compact left sidebar (Today, Pupils, Diary, Money + Settings), main canvas
- Mobile: sticky top brand bar + 5-column bottom tabs (Today, Pupils, Diary, Money, New)
- Content max-width for operational pages: ~720px (`--content-max-width`); diary/money may use `--content-wide-max`

### App type roles

| Role | Use |
|------|-----|
| Page title | Haas Disp ~24–28px |
| Section title | Haas Disp ~22px |
| Metric | Haas Disp ~32px, tabular nums |
| Body / controls | Haas Text 16px |
| Meta / labels | 13px muted — prefer readable text over mono for ops chrome |
| Caption mono | Sparingly (tags), not for navigation |

### App surfaces

| Surface | Token / value |
|---------|----------------|
| Canvas | `--color-chalk-green` |
| Sidebar | soft frost wash (`--surface-sidebar`) |
| Card | paper white + 1px frost border + hard 1px offset shadow |
| Soft panel | frost wash, no hard shadow |
| List rows | flush panel with hairline dividers (not a card per row) |

### Semantic meaning (not decorative)

| Meaning | Wash | Text |
|---------|------|------|
| Success | `--color-success-wash` | OwnLane Green |
| Warning | `--color-warning-wash` | `--color-warning` |
| Danger | `--color-danger-wash` | `--color-danger` |

Do not use Hi-Yellow / Marker Red as operational alert fills — those remain decorative accents for marketing.

### Controls

- Inputs: pill radius, paper fill, frost border, green focus ring
- Textareas: `--radius-textarea` (16px), not full pill
- Primary CTA: OwnLane Green pill + hard ink offset shadow
- Ghost: hairline border, no shadow
- Pupil pickers: searchable combobox — never a long native `<select>`

### Density

Operational screens should feel efficient. Prefer section gap ~28px and card padding ~20px over marketing section gaps (80px).
