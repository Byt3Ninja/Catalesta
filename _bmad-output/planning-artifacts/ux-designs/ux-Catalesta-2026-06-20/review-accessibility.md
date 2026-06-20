# UX Spine Validation — Accessibility + RTL lens (2026-06-20)

Hand-computed WCAG sRGB ratios (±0.1) against the DESIGN.md frontmatter tokens. RTL section judged unusually complete; contrast claim was not kept by several tokens.

## Critical (applied)
- **C-1 Dark-mode primary button** white-on-`#7C5CFF` ≈ **3.6:1**, fails AA normal text — and it's the climax control of both flows (Accept/Reject, Submit). Swapping ink for white (≈3.4:1) also fails. → **Fixed:** added `{accentBtn}` `#5A38E6` (white text ≈5.3:1) as the primary-button fill in both modes.

## High (applied)
- **C-2 `{accent}` violet on white ≈ 4.0:1** fails as normal text; DESIGN L104 self-contradicted ("accent text ≥4.5:1"). → Rule: accent/info/status never normal/small text; use `{accentBtn}`/`{brand}`; DESIGN contrast rules rewritten.
- **C-3 light `{success}` ≈3.3:1 / `{warning}` ≈4.0:1** fail as small badge text. → Darkened to `#0A7D45` / `#9A6410`; status hue confined to icon+pill, label in `{ink}`.
- **F-1 automated a11y gate deferred to P4** undercuts the floor (the contrast claim was already false). → Pulled a minimal contrast + missing-label + lang/dir CI check into P1a.
- **R-1 bidi isolation** specced for table cells but not interpolated sentence copy ("closed on {date}", "Score {startup}"). → Generalized to every interpolated value.
- **K-1 click-only `<tr>`** has no keyboard equivalent. → Per-row focusable "open detail" control distinct from the bulk checkbox.

## Medium (applied / carried)
- **C-5 input border <3:1 (WCAG 1.4.11)** → added `{inputBorder}` ≥3:1 (exact value to confirm with a tool — carried).
- **SR-1** `aria-invalid` + `aria-describedby` field↔error association → added to floor.
- **K-2** modal focus containment / Esc / restore for the two irreversible dialogs → added.
- **R-2** score field `dir="ltr"` + logical alignment → fixed in DESIGN.
- **R-3** Arabic-locale dates can yield Eastern digits via `Intl` → pinned `-nu-latn`.
- **C-4** disabled reason text must use `{inkMuted}` not `{inkFaint}` → fixed.
- SR-2/3/4/5 (icon aria-hidden, score max in a11y name, skeleton aria-busy, toast live region, table semantics) → folded into the floor.

## Low (carried)
- T-1 operator-console touch targets (deferred P4); R-4 directional-icon inventory; T-2/T-3 reduced-motion on shimmer/toast + non-animated loading fallback (noted in floor).

**Net:** the two defects that would have shipped (dark-button contrast, un-isolated bidi copy) are fixed; the floor now carries a CI gate. Exact `{inputBorder}` value to verify with a tool at build.
