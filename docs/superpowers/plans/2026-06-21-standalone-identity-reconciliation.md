# Standalone Identity PRD Reconciliation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Edit the planning SSOT (PRD, epics, architecture, CLAUDE.md, sprint-status, memory) so Catalesta is documented as the system of record for identity and Startup Gate is an optional linked SSO + consented import source.

**Architecture:** Pure documentation/SSOT edits per the §4 ledger of `docs/superpowers/specs/2026-06-21-standalone-identity-design.md`. No code. The "test" for each task is a `grep` consistency check: the stale assertion is gone, the new one is present. SP-1..SP-4 (actual code) are separate downstream brainstorm→plan→build cycles and are **out of scope here**.

**Tech Stack:** Markdown + YAML. Edits via the Edit tool. Verification via `grep`/`rg`.

## Global Constraints

- Source of truth for every edit: `docs/superpowers/specs/2026-06-21-standalone-identity-design.md` (the approved design). Where this plan and the spec disagree, the spec wins.
- Identifier rule (copy verbatim into edits): **Account id (ULID) is the primary user identifier; a Startup Gate `sub`, when linked, is the immutable identifier of that linked external identity only; email is a local login credential, never a cross-system identifier.**
- Role taxonomy: 7 role-profile types — Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge (Evaluator→Judge, Funder→Investor). Operator/Admin + Platform Admin remain RBAC roles, not profiles.
- Sequencing: a new **Epic 4 "Standalone Identity, Accounts & Profiles"** runs after Epic 2's in-review stories close and **before Epic 3**.
- Do NOT rewrite the history of already-`done` Epic 1 stories. Where FR-001's meaning changes, record the supersession as a forward note (the "Epic 1 impact ledger"), not by editing closed story files.
- FR ID scheme: block-allocated with reserved gaps (PRD §1 line 15). FR-007/008/009 are the natural free slots in the Identity block (001–006 used). Do not renumber existing FRs.
- All work on branch `feat/standalone-identity-prd` (already checked out). One commit per task.

---

### Task 1: CLAUDE.md — rewrite rules 2, 4, 5, 11

**Files:**
- Modify: `CLAUDE.md` (Non-Negotiable Rules list)

**Interfaces:**
- Produces: the canonical rule wording every other task's edits must stay consistent with.

- [ ] **Step 1: Edit rule 2.** Replace:

```
2. Startup Gate owns global identity, general profiles, role profiles, startup memberships, consent, verification, shared directories, and achievements.
```

with:

```
2. Catalesta owns global identity, accounts, general and role profiles, memberships, and consent as system of record. Startup Gate is an optional external identity provider (SSO) and a consented profile-import source — never the system of record.
```

- [ ] **Step 2: Edit rule 4.** Replace:

```
4. Use Startup Gate `sub` as the immutable user identifier.
```

with:

```
4. The primary user identifier is the local Account id (ULID). A Startup Gate `sub`, when an account is linked, is the immutable identifier of that linked external identity — unique, never reassigned, never the primary key.
```

- [ ] **Step 3: Edit rule 5.** Replace:

```
5. Never use email as the cross-system identifier.
```

with:

```
5. Email is a local login credential only. Never use email as a cross-system, cross-tenant, or external-linkage identifier; use the Account id locally and `sub` for Startup Gate linkage.
```

- [ ] **Step 4: Edit rule 11.** Replace:

```
11. All profile access must be consent-aware.
```

with:

```
11. All profile access must be consent-aware, including locally-owned profiles. Importing any field from Startup Gate requires explicit, field-level consent; imported data is a local editable copy and must never auto-overwrite locally modified fields.
```

- [ ] **Step 5: Verify.** Run:

```bash
grep -nE "Startup Gate owns global identity|Use Startup Gate `sub` as the immutable" CLAUDE.md && echo "STALE FOUND — FAIL" || echo "OK: stale rule text gone"
grep -nE "Catalesta owns global identity, accounts|primary user identifier is the local Account id" CLAUDE.md
```

Expected: first line prints `OK: stale rule text gone`; second prints the two new rule lines.

- [ ] **Step 6: Commit.**

```bash
git add CLAUDE.md
git commit -m "docs(rules): invert identity ownership in CLAUDE.md rules 2/4/5/11

Catalesta is system of record; Startup Gate optional SSO + import.
Account id (ULID) primary key; sub is the SG-link key; email local-only.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: prd.md — overview, identity FRs, NFRs, data ownership, FR-157

**Files:**
- Modify: `_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md` (lines per step; line numbers as of this plan — match on text, not number)

**Interfaces:**
- Consumes: rule wording from Task 1.
- Produces: FR-007/008/009 (referenced by Task 4's epics mapping and Task 5's sprint stories).

- [ ] **Step 1: §1 Overview — replace the delegation clause.** Replace:

```
Laravel modular monolith; identity is delegated to Startup Gate (`sub` is the immutable user key, never email).
```

with:

```
Laravel modular monolith. **Catalesta owns identity** — native registration, authentication, and locally-owned multi-role profiles; the Account id (ULID) is the immutable user key and email is a local login credential only. Startup Gate is an **optional** linked identity provider (SSO) and a consented profile-import source, never the system of record — the platform is fully operational without it.
```

- [ ] **Step 2: FR-001 — native auth.** Replace the whole FR-001 bullet:

```
- **FR-001** A user authenticates via Startup Gate OIDC; the immutable `sub` is the user key; email is never an identifier. **Phase 1a runs against the Startup Gate OIDC *mock*** (real-provider cutover is FR-157); FR-001 is "done" for P1a when it passes against the mock and the adapter interface is provider-agnostic — it is **not** claimed production-validated until FR-157.
```

with:

```
- **FR-001** A user authenticates with a **native Catalesta account** (email + password) or, optionally, via a linked identity provider; the immutable **Account id (ULID)** is the user key and email is a local login credential, never a cross-system identifier. Sessions use the existing Sanctum SPA cookie-session transport. *(Native accounts + the linked-provider model are delivered by Epic 4 / SP-1–SP-2; the shipped Epic-1 SG-OIDC-mock path is superseded — see the Epic 1 impact ledger in `epics.md`.)*
```

- [ ] **Step 3: FR-006 — consent over local profiles.** Replace:

```
- **FR-006** Profile reads are consent-aware. **Phase 1a consent source is the Startup Gate mock**; FR-006 is "done" for P1a when the `ConsentProvider` interface is enforced at every profile-read call site against the mock — production consent integration lands with FR-157. (CLAUDE #11 is satisfied as *enforced seam*, not as production data, in P1a.)
```

with:

```
- **FR-006** Profile reads are consent-aware, **including locally-owned profiles**; the `ConsentProvider` interface is enforced at every profile-read call site. (CLAUDE #11.)
```

- [ ] **Step 4: Add FR-007/008/009** immediately after the FR-006 bullet (still in §6.1):

```
- **FR-007** A user can **register a native account** (email + password), verify their email, reset a forgotten password, and manage their session — with no Startup Gate dependency. *(Epic 4 / SP-1.)*
- **FR-008** A user can **link** an optional Startup Gate identity to their Catalesta account and sign in with it, or **unlink** it; the account remains usable after unlink. `sub` is stored on the link, not the account. *(Epic 4 / SP-2.)*
- **FR-009** A user can **import selected profile fields from Startup Gate after explicit, field-level consent**; imported data is a local editable copy with per-field source tracking, import history, and a conflict preview, and **never auto-overwrites locally modified fields**; consent is revocable. *(Epic 4 / SP-4.)*
```

- [ ] **Step 5: §4 Users — applicant auth line.** Replace:

```
Authenticates via Startup Gate `sub` [ASSUMPTION].
```

with:

```
Authenticates with a native Catalesta account, or optionally a linked Startup Gate identity.
```

- [ ] **Step 6: NFR-002.** Replace:

```
- **NFR-002 Identity integrity** — Startup Gate `sub` is the only cross-system key; email never identifies.
```

with:

```
- **NFR-002 Identity integrity** — the **Account id (ULID)** is the primary user identifier; a Startup Gate `sub`, when linked, is the immutable key of that external identity only. Email never identifies across systems.
```

- [ ] **Step 7: NFR-006.** Replace:

```
- **NFR-006 Consent-aware access** — all profile reads enforce consent state via the `ConsentProvider` seam (mock in P1a; real at FR-157).
```

with:

```
- **NFR-006 Consent-aware access** — all profile reads (including locally-owned profiles) enforce consent state via the `ConsentProvider` seam; importing any field from Startup Gate requires explicit field-level consent.
```

- [ ] **Step 8: §9 Data Ownership — invert the ownership arrow.** Replace:

```
- **Startup Gate (global identity domain):** identity (`sub`), general + role profiles, startup memberships, consent, verification, shared directories, achievements. No direct DB sharing; the platform reads via adapter interfaces (mock in P1a, real at FR-157).
- **Program Platform (tenant domain):** organizations, programs, cohorts, stages, forms, applications, documents, assessments, workflows, role assignments, tasks, mentorship, training, final evaluation, graduation, reporting. Join key is `sub`.
```

with:

```
- **Catalesta (system of record):** accounts & identity, general + role profiles, memberships, consent, verification. Owns all user, role, program, operational, assessment, document, and reporting data. The 7 role-profile types are Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge.
- **Startup Gate (optional external identity domain):** an optional linked SSO provider and a consented, field-level profile-import source. No direct DB sharing; the platform integrates via adapter interfaces. Imported data is a local editable copy and never auto-overwrites local edits.
- **Program Platform (tenant domain):** organizations, programs, cohorts, stages, forms, applications, documents, assessments, workflows, role assignments, tasks, mentorship, training, final evaluation, graduation, reporting. Join key is the **Account id**.
```

- [ ] **Step 9: FR-157 — demote.** In the FR-150 block, replace:

```
**FR-157** Real Startup Gate cutover (mock → production) + federated SSO;
```

with:

```
**FR-157** Startup Gate as an **optional** linked SSO provider + consented profile import (no authority cutover — SG never becomes the system of record);
```

- [ ] **Step 10: Phase-4 table row.** Replace:

```
real Startup Gate (FR-157)
```

with:

```
optional Startup Gate SSO + import (FR-157)
```

- [ ] **Step 11: Verify.** Run:

```bash
PRD=_bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
grep -nE "identity is delegated to Startup Gate|`sub` is the only cross-system key|Startup Gate \(global identity domain\)|Real Startup Gate cutover" "$PRD" && echo "STALE FOUND — FAIL" || echo "OK: stale prd assertions gone"
grep -nE "FR-007|FR-008|FR-009|Catalesta \(system of record\)|Account id \(ULID\) is the primary" "$PRD"
```

Expected: first prints `OK: stale prd assertions gone`; second prints the new FR-007/008/009 bullets and the new ownership/identity lines.

- [ ] **Step 12: Commit.**

```bash
git add _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
git commit -m "docs(prd): standalone identity — native auth, FR-007/008/009, invert §9 ownership

FR-001 native auth; add FR-007 (register) / FR-008 (link SG) / FR-009
(consented import); NFR-002/006 reframed; §9 ownership inverted to
Catalesta system-of-record; FR-157 demoted to optional SSO+import.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: architecture.md — reframe the 4 SG-delegation assertions

**Files:**
- Modify: `_bmad-output/planning-artifacts/architecture.md`

**Interfaces:**
- Consumes: rule wording (Task 1), PRD §9 (Task 2).

- [ ] **Step 1: Reuse line (identity).** Replace:

```
- Reuse (built): Identity/`sub` auth (mock), Organizations/tenancy/RBAC, Programs, Cohorts, Stages, versioning/immutability, decimal rule kernel.
```

with:

```
- Reuse (built): Identity/auth (Sanctum SPA session), Organizations/tenancy/RBAC, Programs, Cohorts, Stages, versioning/immutability, decimal rule kernel. **(Identity inverts to native Catalesta accounts under Epic 4 — see PRD §9; the `sub`-keyed SG-OIDC path demotes to an optional linked provider.)**
```

- [ ] **Step 2: External-integrations line.** Replace:

```
- External integrations behind interfaces only: Startup Gate OIDC (mock in P1a → real FR-157), Geidea (sandbox, no real charge in P1a).
```

with:

```
- External integrations behind interfaces only: Startup Gate OIDC as an **optional** linked SSO/import provider (FR-157), Geidea (sandbox, no real charge in P1a).
```

- [ ] **Step 3: Non-negotiables line.** Replace:

```
- Non-negotiables (CLAUDE.md): organization_id on every tenant row; `sub` not email; no raw card/CVV; published artifacts immutable.
```

with:

```
- Non-negotiables (CLAUDE.md): organization_id on every tenant row; **Account id (ULID) is the primary user key, `sub` is the SG-link key, email is a local credential only**; no raw card/CVV; published artifacts immutable.
```

- [ ] **Step 4: "Already fixed" line.** Replace:

```
- **Already fixed:** fail-closed `BelongsToTenant`; decimal kernel; versioning/immutability kernel; Sanctum SPA + JWKS-OIDC. No init story needed.
```

with:

```
- **Already fixed:** fail-closed `BelongsToTenant`; decimal kernel; versioning/immutability kernel; Sanctum SPA + JWKS-OIDC (the JWKS-OIDC path becomes the optional SG-link adapter under Epic 4; native-account auth is net-new). No tenancy/kernel init story needed.
```

- [ ] **Step 5: Verify.** Run:

```bash
ARCH=_bmad-output/planning-artifacts/architecture.md
grep -nE "Identity/`sub` auth \(mock\)|Startup Gate OIDC \(mock in P1a → real FR-157\)|`sub` not email" "$ARCH" && echo "STALE FOUND — FAIL" || echo "OK: stale arch assertions gone"
grep -nE "Identity inverts to native Catalesta accounts|optional linked SSO/import provider|Account id \(ULID\) is the primary user key" "$ARCH"
```

Expected: first prints `OK: stale arch assertions gone`; second prints the three reframed lines.

- [ ] **Step 6: Commit.**

```bash
git add _bmad-output/planning-artifacts/architecture.md
git commit -m "docs(arch): reframe SG-delegation assertions to optional linked provider

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: epics.md — FR/NFR inventory, new Epic 4, Epic 1 impact ledger, Epic 3 gate

**Files:**
- Modify: `_bmad-output/planning-artifacts/epics.md`

**Interfaces:**
- Consumes: FR-007/008/009 (Task 2).
- Produces: the Epic 4 + SP-1..SP-4 structure mirrored by Task 5's sprint-status stories.

- [ ] **Step 1: FR inventory — FR-001.** Replace:

```
- FR-001: Authenticate via Startup Gate OIDC; immutable `sub` is the user key (mock in P1a; "done" = passes vs mock + provider-agnostic adapter).
```

with:

```
- FR-001: Authenticate with a native Catalesta account (email + password) or an optional linked provider; immutable Account id (ULID) is the user key. (SG OIDC demotes to an optional linked provider — Epic 4.)
```

- [ ] **Step 2: FR inventory — FR-006.** Replace:

```
- FR-006: Profile reads are consent-aware via the `ConsentProvider` seam (mock in P1a).
```

with:

```
- FR-006: Profile reads are consent-aware via the `ConsentProvider` seam, including locally-owned profiles.
```

- [ ] **Step 3: Add FR-007/008/009** immediately after the FR-006 inventory bullet:

```
- FR-007: Native account registration + email verification + password reset + session (Epic 4 / SP-1).
- FR-008: Link/unlink an optional Startup Gate identity; sign in with SG; `sub` stored on the link (Epic 4 / SP-2).
- FR-009: Consented field-level profile import from SG — source tracking, import history, conflict preview, never auto-overwrite local edits, revocable consent (Epic 4 / SP-4).
```

- [ ] **Step 4: NFR inventory — NFR-002.** Replace:

```
- NFR-002: Identity integrity — `sub` is the only cross-system key; email never identifies.
```

with:

```
- NFR-002: Identity integrity — Account id (ULID) is the primary user key; `sub` is the SG-link key only; email never identifies across systems.
```

- [ ] **Step 5: NFR inventory — NFR-006.** Replace:

```
- NFR-006: Consent-aware access via `ConsentProvider` seam (mock P1a).
```

with:

```
- NFR-006: Consent-aware access via `ConsentProvider` seam, including locally-owned profiles; SG import requires field-level consent.
```

- [ ] **Step 6: Epic 3 — add the sequencing gate.** Replace:

```
### Epic 3: Score & Decide *(gated on Epic 2 evidence)*
```

with:

```
### Epic 3: Score & Decide *(gated on Epic 2 evidence; sequenced AFTER Epic 4)*
```

- [ ] **Step 7: Insert the Epic 4 list entry** immediately after the Epic 3 list entry's `**FRs covered:** 040, 041, 042, 043, 081.` line (before the `### Cross-cutting deliverable` heading):

```
### Epic 4: Standalone Identity, Accounts & Profiles *(foundational — runs after Epic 2 review closes, BEFORE Epic 3)*
Catalesta becomes the system of record for accounts and identity. Native registration/auth/account-management and locally-owned multi-role profiles; Startup Gate demotes to an optional linked SSO provider + consented import source. Delivered as four dependency-ordered sub-projects, each with its own brainstorm→spec→plan cycle: **SP-1** native accounts & auth (local `accounts` + N `linked_identities`; migrate existing `ExternalUser` rows; memberships repoint to Account) → **SP-2** SG OIDC as an optional linked provider (link/unlink) → **SP-3** the 7 local role-profile types (Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge; system of record) → **SP-4** consented SG import pipeline (field-level consent, source tracking, history, conflict preview, never-overwrite-local, revocation).
**FRs covered:** 007, 008, 009 (+ supersedes the SG-mock framing of 001/006). **Spec:** `docs/superpowers/specs/2026-06-21-standalone-identity-design.md`.

**Epic 1 impact ledger (forward note — do NOT edit closed story files):** Story 1.1 shipped sign-up as "first SG-OIDC-mock login → create org," and the auth provider is `ExternalUser` keyed on `sub`. Epic 4 / SP-1 supersedes this: sign-up becomes native account registration, `ExternalUser` rows migrate to `accounts` + `linked_identities`, and `organization_memberships` repoint to `account_id`. Epic 2's in-review stories keep working across the migration and adopt the account model when SP-1 lands.
```

- [ ] **Step 8: Verify.** Run:

```bash
EP=_bmad-output/planning-artifacts/epics.md
grep -nE "Authenticate via Startup Gate OIDC; immutable `sub`|`sub` is the only cross-system key" "$EP" && echo "STALE FOUND — FAIL" || echo "OK: stale epics inventory gone"
grep -nE "Epic 4: Standalone Identity|Epic 1 impact ledger|FR-007:|sequenced AFTER Epic 4" "$EP"
```

Expected: first prints `OK: stale epics inventory gone`; second prints the Epic 4 heading, the impact-ledger line, the FR-007 bullet, and the Epic 3 gate.

- [ ] **Step 9: Commit.**

```bash
git add _bmad-output/planning-artifacts/epics.md
git commit -m "docs(epics): add Epic 4 (standalone identity), Epic 1 impact ledger, sequence Epic 3 after

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: sprint-status.yaml — add epic-4 + SP stories, gate epic-3

**Files:**
- Modify: `_bmad-output/implementation-artifacts/sprint-status.yaml`

**Interfaces:**
- Consumes: the Epic 4 / SP-1..SP-4 structure from Task 4.

- [ ] **Step 1: Annotate the epic-3 line** to record the new gate. Replace:

```
  # ── Epic 3: Score & Decide (gated on Epic 2 evidence) ──
  epic-3: backlog
```

with:

```
  # ── Epic 3: Score & Decide (gated on Epic 2 evidence; sequenced after Epic 4) ──
  epic-3: backlog  # do not start until Epic 4 (SP-1) lands the account model
```

- [ ] **Step 2: Append the epic-4 block** at the end of `development_status` (after the `epic-3-retrospective: optional` line):

```
  # ── Epic 4: Standalone Identity, Accounts & Profiles (foundational — runs before Epic 3) ──
  epic-4: backlog  # spec: docs/superpowers/specs/2026-06-21-standalone-identity-design.md
  4-1-sp1-native-accounts-and-auth: backlog        # local accounts + linked_identities; migrate ExternalUser; memberships → account_id
  4-2-sp2-startup-gate-linked-provider: backlog    # optional SG OIDC link/unlink; sign in with SG
  4-3-sp3-local-role-profiles: backlog             # 7 role-profile types; system of record
  4-4-sp4-consented-import-pipeline: backlog        # field-level consent, source tracking, history, conflict preview, never-overwrite, revocation
  epic-4-retrospective: optional
```

> Note: each `4-x` line is a **sub-project placeholder**, not a single story. Each SP gets its own brainstorm→spec→plan that will expand it into real stories at create-story time.

- [ ] **Step 3: Bump `last_updated`** (both the comment on line ~2 and the field on line ~46) if not already `2026-06-21`. It is currently `2026-06-21` — confirm and leave as-is.

- [ ] **Step 4: Verify.** Run:

```bash
SS=_bmad-output/implementation-artifacts/sprint-status.yaml
grep -nE "epic-4:|4-1-sp1-native-accounts|4-4-sp4-consented-import|sequenced after Epic 4" "$SS"
python3 -c "import yaml,sys; yaml.safe_load(open('$SS')); print('OK: yaml parses')"
```

Expected: the four grep lines present; `OK: yaml parses`.

- [ ] **Step 5: Commit.**

```bash
git add _bmad-output/implementation-artifacts/sprint-status.yaml
git commit -m "chore(sprint): add epic-4 SP-1..SP-4 backlog; gate epic-3 behind epic-4

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: memory — reverse the "Startup Gate owns identity" decision

**Files:**
- Modify: `/Users/byteninja/.claude/projects/-Users-byteninja-Downloads-GrowthLabs-Catalesta/memory/architecture-decisions.md`
- Modify: `/Users/byteninja/.claude/projects/-Users-byteninja-Downloads-GrowthLabs-Catalesta/memory/MEMORY.md`

**Interfaces:**
- Consumes: the final direction from all prior tasks.

> Note: memory files live under `~/.claude/...`, outside the repo — they are NOT part of the git commit. Edit them directly; no commit step.

- [ ] **Step 1: Update the intro line** of `architecture-decisions.md`. Replace:

```
Decisions resolving contradictions found in the docs pack, governing all build phases (1–4 made 2026-06-18; 5 added 2026-06-20):
```

with:

```
Decisions resolving contradictions found in the docs pack, governing all build phases (1–4 made 2026-06-18; 5 added 2026-06-20; 6 added 2026-06-21):
```

- [ ] **Step 2: Append decision 6** immediately after the decision-5 bullet (the line starting `5. **Cross-tenant org access returns neutral 404`):

```
6. **Identity ownership inverted — Catalesta is the system of record** (decided 2026-06-21). Supersedes the earlier "Startup Gate owns identity" stance baked into the original PRD §9 / CLAUDE.md rules 2/4/5/11. Catalesta owns accounts, identity, general + role profiles, memberships, and consent; Startup Gate is an **optional** linked SSO provider + consented field-level import source, never authoritative. Primary user key = local **Account id (ULID)**; `sub` lives on a `linked_identities` row (the SG link), not on the account; email is a local login credential only. Seven role-profile types: Founder, Startup, Mentor, Service Provider, Investor, Trainer, Judge (Evaluator→Judge, Funder→Investor; Operator/Admin + Platform Admin stay RBAC roles). Delivered by new **Epic 4** (foundational, before Epic 3) as SP-1..SP-4. Design: `docs/superpowers/specs/2026-06-21-standalone-identity-design.md`. **Epic-1 impact:** Story 1.1's "first SG-OIDC-mock login → create org" sign-up and the `ExternalUser`-keyed-on-`sub` model are superseded; SP-1 migrates `external_users` → `accounts` + `linked_identities` and repoints `organization_memberships` to `account_id`.
```

- [ ] **Step 3: Update the `description:` frontmatter** of `architecture-decisions.md`. Replace:

```
description: Resolved doc-contradiction decisions governing Catalesta platform implementation
```

with:

```
description: Resolved doc-contradiction decisions governing Catalesta platform implementation (incl. identity-ownership inversion → Catalesta system of record, 2026-06-21)
```

- [ ] **Step 4: Update the MEMORY.md index line.** Replace:

```
- [Architecture Decisions](architecture-decisions.md) — 5 confirmed resolutions to doc contradictions (cohort naming, 24 modules, backend/frontend/services layout, shared rule kernel + early infra, cross-tenant org access → 404 not 403)
```

with:

```
- [Architecture Decisions](architecture-decisions.md) — 6 confirmed resolutions to doc contradictions (cohort naming, 24 modules, backend/frontend/services layout, shared rule kernel + early infra, cross-tenant org access → 404 not 403, identity ownership inverted → Catalesta system of record / SG optional SSO+import)
```

- [ ] **Step 5: Verify.** Run:

```bash
MEM=/Users/byteninja/.claude/projects/-Users-byteninja-Downloads-GrowthLabs-Catalesta/memory
grep -nE "6 added 2026-06-21|Identity ownership inverted — Catalesta is the system of record" "$MEM/architecture-decisions.md"
grep -nE "identity ownership inverted" "$MEM/MEMORY.md"
```

Expected: decision-6 text present in `architecture-decisions.md`; updated index line present in `MEMORY.md`.

---

### Task 7: Cross-file consistency sweep (final gate)

**Files:**
- Read-only sweep across all edited files.

- [ ] **Step 1: No stale ownership assertions remain anywhere in the SSOT.** Run:

```bash
rg -n "delegated to Startup Gate|Startup Gate owns global identity|Startup Gate \(global identity domain\)|`sub` is the only cross-system key|Use Startup Gate `sub` as the immutable|Real Startup Gate cutover" \
  CLAUDE.md _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md \
  _bmad-output/planning-artifacts/architecture.md _bmad-output/planning-artifacts/epics.md \
  && echo "STALE FOUND — FAIL" || echo "OK: no stale ownership assertions in SSOT"
```

Expected: `OK: no stale ownership assertions in SSOT`.

- [ ] **Step 2: New direction is present and consistent.** Run:

```bash
rg -n "Account id \(ULID\)" CLAUDE.md _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md _bmad-output/planning-artifacts/architecture.md _bmad-output/planning-artifacts/epics.md
rg -n "FR-007|FR-008|FR-009" _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md _bmad-output/planning-artifacts/epics.md
```

Expected: `Account id (ULID)` appears in all four files; FR-007/008/009 appear in both prd.md and epics.md.

- [ ] **Step 3: FR-007/008/009 are not double-allocated.** Run:

```bash
rg -nc "FR-007|FR-008|FR-009" _bmad-output/planning-artifacts/prds/prd-Catalesta-2026-06-20/prd.md
```

Expected: a small count (the new bullets only) — confirm no pre-existing FR-007/008/009 elsewhere collided.

- [ ] **Step 4: Final commit (sweep is clean — nothing to change, but tag the milestone).** Only if any fixups were needed in steps 1–3; otherwise skip. If fixups were made:

```bash
git add -A
git commit -m "docs: fix residual identity-ownership inconsistencies found in sweep

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (§4 ledger of the design → task):
- prd.md §1 / §9 / FR-001 / FR-006 / FR-007/008/009 / NFR-002 / NFR-006 / FR-157 / Phase-4 row / §4 users → **Task 2** ✓
- epics.md FR+NFR inventory / Epic 4 / Epic 1 ledger / Epic 3 gate → **Task 4** ✓
- architecture.md 4 assertions → **Task 3** ✓
- CLAUDE.md rules 2/4/5/11 → **Task 1** ✓
- sprint-status.yaml epic-4 + gate → **Task 5** ✓
- architecture-decisions memory (+ MEMORY.md index) → **Task 6** ✓
- Cross-file consistency → **Task 7** ✓

**Placeholder scan:** every edit shows exact old + new text; no "TBD"/"handle appropriately." The `4-x` sprint lines are deliberately labelled sub-project placeholders (each spawns its own plan), which is a real status, not a plan placeholder. ✓

**Type/term consistency:** "Account id (ULID)" used verbatim across Tasks 1–4 and 6; "linked_identities" / `sub`-on-the-link consistent; 7 role types listed identically in Task 2 §9, Task 4 Epic 4, Task 6 decision 6. ✓

**Out of scope (restated):** no SP-1..SP-4 code, no migrations, no backend/frontend changes — those are downstream cycles starting with an SP-1 brainstorm.
