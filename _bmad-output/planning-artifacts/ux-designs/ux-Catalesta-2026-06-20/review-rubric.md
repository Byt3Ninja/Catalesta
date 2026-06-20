# UX Spine Validation — rubric walker (2026-06-20)

**Verdict:** strong, build-ready spines. Clean DESIGN↔EXPERIENCE split, full token-reference integrity, every P1a surface mapped to a PRD FR, concrete RTL/bilingual + accessibility floor.

## Dimension verdicts
Coverage **adequate** (→strong) · DESIGN↔EXPERIENCE coherence **strong** · Buildability **adequate** · State completeness **strong** · RTL & bilingual **strong** · Accessibility floor **strong** · Flows & shape fit **strong**.

## High findings (applied this pass)
- **Signup/org-create + auth surfaces** had no IA entry and no states — the product's first screens. → Added IA entries + State Patterns (auth pending/failure/IdP-unavailable/return; signup empty/validation/accepted/error).
- **FR-062 limit banner** specced as full P1a but FR-060 is allow-all in P1a → banner has no live trigger until P1b. → Phase note added.
- **Stepped-form pattern** left as "multi-step or section-tracked" (load-bearing for drop-off). → Pinned: multi-step, Next/Back, progress, per-section autosave.
- **Form assembly** ambiguity (builder vs attach). → Clarified P1a is attach-only; full builder is P3 (FR-127).

## Low (applied)
- "both spines win" vs "this spine wins" → clarified DESIGN governs visual, EXPERIENCE behavior.
- RTL test-scope inconsistency ("every screen" vs "4 targets, 2 screens") → carried as an Update item.

## Mechanical
- Token-ref integrity clean; FR cross-refs all resolve; instrumentation reconciled to FR-080.
