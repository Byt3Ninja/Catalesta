---
status: final
created: 2026-06-20
updated: 2026-06-20
direction: Modern Editorial
modes: [light, dark]
colors:
  light:
    bg: "#F7F7FB"
    surface: "#FFFFFF"
    surfaceAlt: "#F1EFFA"
    ink: "#1A1430"
    inkMuted: "#5B5470"
    inkFaint: "#8E87A6"
    brand: "#241A47"
    accent: "#6D4AFF"
    accentHover: "#5A38E6"
    accentSubtle: "#EFEAFF"
    border: "#E6E3F0"
    borderStrong: "#CFC9E4"
    inputBorder: "#79718F"   # ≥3:1 on surface (WCAG 1.4.11) — use for input/control boundaries
    accentBtn: "#5A38E6"     # primary-button fill: white text ≥4.5:1 (the {accent} violet fails as a text bg)
    success: "#0A7D45"       # darkened so a small status label passes 4.5:1 on surface
    warning: "#9A6410"       # darkened so a small status label passes 4.5:1 on surface
    danger: "#D92D20"
    info: "#5A38E6"          # = accentBtn; info text/links must pass, the raw accent does not
    focus: "#6D4AFF"
  dark:
    bg: "#14121F"
    surface: "#1E1B2E"
    surfaceAlt: "#26223A"
    ink: "#ECE9F7"
    inkMuted: "#A9A2C2"
    inkFaint: "#7C7596"
    brand: "#B6A6FF"
    accent: "#7C5CFF"
    accentHover: "#9279FF"
    accentSubtle: "#2A2342"
    border: "#2E2A40"
    borderStrong: "#403A58"
    inputBorder: "#6E6788"   # ≥3:1 on dark surface (WCAG 1.4.11)
    accentBtn: "#5A38E6"     # primary-button fill: white text ≥4.5:1 in dark too (raw {accent} #7C5CFF = ~3.6:1, fails)
    success: "#34C77B"
    warning: "#F2C14E"
    danger: "#FF6B5E"
    info: "#8A6CFF"
    focus: "#9279FF"
typography:
  display: "'Space Grotesk', system-ui, sans-serif"
  body: "'Inter', system-ui, sans-serif"
  arabic: "'Tajawal', system-ui, sans-serif"
  mono: "'IBM Plex Mono', ui-monospace, monospace"
  scale:
    display: "28/34 700"
    h1: "22/28 600"
    h2: "18/24 600"
    bodyLg: "16/24 400"
    body: "15/22 400"
    small: "13/18 400"
    caption: "12/16 500"
  numeric: "tabular-nums for scores/IDs/counts"
rounded:
  sm: "8px"
  md: "10px"
  lg: "14px"
  pill: "999px"
spacing:
  base: "4px"
  scale: [4, 8, 12, 16, 20, 24, 32, 40, 56]
  density: comfortable
  rowPaddingY: "12px"
  sectionGap: "24px"
  contentMax: "1200px"
  readingMax: "720px"
components:
  button: { radius: md, padH: "16px", padV: "10px", weight: 600 }
  input: { radius: md, border: "1.5px", padH: "12px", padV: "10px", focusRing: "focus" }
  card: { radius: lg, border: "1px", pad: "20px", shadow: e1 }
  tableRow: { radius: md, padY: "12px", hover: surfaceAlt }
  banner: { radius: md, pad: "12px 16px" }
  badge: { radius: pill, padH: "8px", padV: "2px", weight: 600, size: caption }
  modal: { radius: lg, maxW: "560px", shadow: e3 }
  scoreInput: { radius: md, width: "96px", align: right, font: mono, numeric: tabular-nums }
elevation:
  e0: "none"
  e1: "0 1px 3px rgba(20,15,40,.08)"
  e2: "0 4px 12px rgba(20,15,40,.10)"
  e3: "0 12px 32px rgba(20,15,40,.16)"
---

# Catalesta — DESIGN.md (visual identity)

Owns *how it looks*. `EXPERIENCE.md` owns *how it works* and references these tokens by `{name}`. On any conflict with a mock or import, this spine wins. Scope: **Phase 1a (Selection MVP)**; light + dark; Latin + Arabic (RTL).

## Brand & Style

**Direction: Modern Editorial.** Catalesta should read *distinctive and contemporary* — not another generic blue B2B SaaS — while still feeling **trustworthy and defensible** (its core promise is auditable selection). The character comes from the **Space Grotesk** display voice and a confident **violet accent** over a deep indigo brand; everything else stays quiet so the *data is the hero*. Comfortable density: breathing room over cramming, because first-use clarity matters more than power-user throughput at MVP.

Voice in pixels: calm surfaces, one decisive accent, generous whitespace, tabular numerals for anything scored or counted. Never decorative for its own sake — every weight/color earns a meaning.

## Colors

Two modes; tokens in frontmatter. Semantics:
- **`{brand}`** indigo — top-level chrome, headings, the wordmark. Not for large fills in dark mode (use `{ink}`/`{accent}`).
- **`{accent}`** violet — the brand action hue, used for **fills** (selected state), large display numerals, and ≥3:1 UI strokes. **Primary buttons fill with `{accentBtn}`** (a darker violet) so white label text passes AA in both modes — the raw `{accent}` fails as a text background.
- **`{accentSubtle}`** — selected rows, active nav, info-banner backgrounds.
- **Status** — `{success}` accepted, `{danger}` rejected/destructive, `{warning}` limit-approaching, `{info}` neutral system. **Status never relies on color alone** (always icon + text).
- **Contrast rules (verified against tokens):**
  - Body/secondary text uses `{ink}` / `{inkMuted}` — both ≥ 4.5:1 on `{surface}` in both modes.
  - **`{accent}`, `{info}`, and the status hues are NEVER used for normal- or small-size text** on `{surface}`/`{bg}`. Text that must carry the accent uses `{accentBtn}`/`{brand}`; status *labels* use `{ink}` with the hue confined to the icon + pill background (the darkened `{success}`/`{warning}` pass as small text where used directly).
  - **Input/control boundaries use `{inputBorder}` (≥ 3:1, WCAG 1.4.11)** — the decorative `{border}`/`{borderStrong}` are for non-essential separation only.
  - Focus ring `{focus}` ≥ 3:1 as a UI object in both modes; never removed.
  - Disabled control may be low-contrast, but its **reason/helper text uses `{inkMuted}`** (≥ 4.5:1), never `{inkFaint}`.

## Typography

- **Display — Space Grotesk** (`{typography.display}`): page titles, the wordmark, large numerals. Used sparingly.
- **Body/UI — Inter** (`{typography.body}`): all interface text, labels, tables, inputs.
- **Arabic — Tajawal** (`{typography.arabic}`): applied to any Arabic run via `lang="ar"`/`dir="rtl"`. Tajawal pairs tonally with Inter and carries both display and body weight in Arabic (Space Grotesk is Latin-only — Arabic headings use Tajawal at heavier weight, never a faux-bold of a Latin face).
- Scale in frontmatter. **Numerals: tabular** for scores, counts, IDs, time-to-decision — columns must align.
- Arabic–Latin mixed runs (Arabic label + Latin/numeric value) keep each run in its own face and direction; see EXPERIENCE.md → RTL & Bilingual Behavior.

## Layout & Spacing

- 4px base; spacing scale in frontmatter. Comfortable density → `{spacing.rowPaddingY}` rows, `{spacing.sectionGap}` between sections.
- Content max `{spacing.contentMax}`; long-form/reading max `{spacing.readingMax}`.
- **Operator console**: two-zone — persistent left rail (context: org → program → cohort) + main work area. **Public application page**: single centered column, mobile-first, max ~`{spacing.readingMax}`.
- Layout is **direction-agnostic**: built with CSS logical properties (inline-start/inline-end), mirrored wholesale under `dir="rtl"`.

## Elevation & Depth

Mostly flat. `{elevation.e1}` cards, `{elevation.e2}` popovers/menus, `{elevation.e3}` modals/toasts. No shadow on rows or inputs — borders carry separation. Dark mode leans on `{surfaceAlt}` over shadow.

## Shapes

Rounded, not pill-everything: `{rounded.md}` controls and inputs, `{rounded.lg}` cards/modals, `{rounded.pill}` only for badges/status. Consistent radius is part of the brand.

## Components

Visual specs (behavior in EXPERIENCE.md → Component Patterns):
- **Button** — primary: **`{accentBtn}` fill**, white text, `{rounded.md}` (AA in both modes); hover `{accentHover}`; secondary: `{inputBorder}` outline on `{surface}`; ghost: text-only. Disabled: `{surfaceAlt}` + `{inkFaint}` fill, **reason text in `{inkMuted}`**, and **always pair with a reason** (EXPERIENCE.md).
- **Input / Select** — **`{inputBorder}` 1.5px** (≥3:1, 1.4.11), `{rounded.md}`; focus = 2px `{focus}` ring (never remove outline). Error: `{danger}` border + helper text + icon, programmatically associated (EXPERIENCE.md → Accessibility Floor).
- **Score input** — narrow, `{typography.mono}` tabular, **content `dir="ltr"`** (the number reads `86.50` LTR even in RTL UI) with **logical inline-end alignment** of the box; shows the rubric max inline (e.g. `86.50 / 100`, the max in the field's accessible description); decimal-only.
- **Table row** — `{tableRow}`; hover `{surfaceAlt}`; selected `{accentSubtle}`; status badge end-aligned; never color-only.
- **Badge/Status pill** — `{rounded.pill}`, `{caption}`, icon + label; mapped to status tokens.
- **Banner** (limit/info/error) — `{rounded.md}`, leading status bar, icon + message + optional action.
- **Card / Modal** — `{rounded.lg}`, `{elevation.e1}`/`{e3}`.
- **File upload** — dropzone with dashed `{border}`, filename + size + remove; progress + error states.
- **Empty state** — centered illustration slot + one-line explanation + the single first action.

## Do's and Don'ts

- **Do** keep one primary `{accent}` action per screen.
- **Do** use tabular numerals for every score/count/ID.
- **Do** convey status with icon **and** text, never color alone.
- **Do** build with logical properties so RTL mirrors for free.
- **Don't** use Space Grotesk for body text or for any Arabic.
- **Don't** introduce a second accent hue; status colors are not accents.
- **Don't** put shadows on rows/inputs; borders separate.
- **Don't** ship faux-bold Arabic — use Tajawal weights.
