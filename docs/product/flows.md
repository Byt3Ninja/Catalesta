# Full Flow Diagram — All Product Flows

> Owner: Product · Last-updated: 2026-06-20
> Sources: `docs/product/lifecycle.md`, `_bmad-output/.../prd.md` (§5 journeys, §6 FRs, §7 phases),
> `_bmad-output/.../ux-designs/.../EXPERIENCE.md` (Key Flows + State Patterns),
> `docs/saas/commercial-architecture.md`, `docs/product/features/*`.
>
> Diagrams are [Mermaid](https://mermaid.js.org/) — they render natively on GitHub.
> Phase tags `[P1a] [P1b] [P2] [P3] [P4]` follow PRD §7. P1a = Selection MVP (live);
> later phases are capability-level and shown for the full picture.

---

## 0. System map — actors → surfaces → modules

```mermaid
flowchart LR
  subgraph Actors
    AP([Applicant])
    OP([Operator / Admin])
    EV([Evaluator])
    MN([Mentor])
    TR([Trainer])
    SA([Platform Super-Admin])
  end

  subgraph Surfaces
    PUB[Public application page<br/>mobile-web, unauth→auth]
    CON[Operator console<br/>Org→Program→Cohort rail]
    BILL[Billing workspace P1b+]
    API[REST API + Webhooks P3]
  end

  subgraph Platform
    SG[[Startup Gate<br/>identity · sub · consent · directories]]
    PP[[Program Platform<br/>orgs · programs · cohorts · stages]]
  end

  AP --> PUB
  OP --> CON
  EV --> CON
  MN --> CON
  TR --> CON
  SA --> CON
  OP --> BILL

  PUB --> SG
  CON --> SG
  PUB --> PP
  CON --> PP
  BILL --> PP
  API --> PP
  SG <-->|sub identity| PP
```

---

## 1. Core participant lifecycle (configurable, per program)

Every program runs this stage chain; all stages are configurable templates driven by
the Stage engine + expression-rule kernel (`docs/product/lifecycle.md`).

```mermaid
flowchart LR
  A[Application<br/>Applications 08] --> E{Eligibility<br/>Workflows 11/12}
  E -->|pass| IE[Initial Evaluation<br/>Assessments 10]
  E -->|fail| RJ[Rejected / waitlist]
  IE -->|accept| MT[Mentorship 14]
  IE -->|reject| RJ
  MT --> TN[Training 15]
  TN --> FE[Final Evaluation 16]
  FE -->|pass| GR[Graduation 17]
  FE -->|fail| RJ
  GR --> AL[Alumni Follow-Up 17]

  RJ -.reopen.-> IE
```

> Stage transitions, entry/exit gates, and personalized-track applicability
> (`features/personalized-tracks.md`) are evaluated by the Stage engine.
> Published stages/forms/rubrics are **immutable + versioned**.

---

## 2. UJ-1 — Operator runs an intake `[P1a]` (happy path + states)

```mermaid
flowchart TD
  START([Operator visits console]) --> AUTH{Authenticated?}
  AUTH -->|no| OIDC[Startup Gate OIDC<br/>redirect handoff]
  OIDC --> AUTH
  AUTH -->|yes, no org| ORG[Create organization<br/>creator becomes admin · FR-002]
  AUTH -->|yes, has org| HOME[Home: cohorts + next action]
  ORG --> HOME

  HOME --> PROG[Create program → Publish · FR-010/012]
  PROG --> COH[Open cohort<br/>set enrollment window · FR-011]
  COH --> FORM[Attach published form<br/>8 field types · FR-020]
  FORM --> SHARE[Copy public link & share]

  SHARE --> SUBS[Submissions list · FR-034]
  SUBS --> DET[Submission detail]
  DET --> SCORE[Score vs versioned rubric<br/>decimal value/max · FR-040<br/>autosave draft → submit]
  SCORE --> DEC{Decision · FR-042}
  DEC -->|accept/reject| COMMIT[Commit decision<br/>sub + time-to-decision · immutable · audited]
  DEC -->|reopen| SCORE
  COMMIT -->|more| SUBS
  COMMIT --> EXPORT[Export decisions CSV · FR-043]

  classDef peak fill:#fde68a,stroke:#b45309;
  class COMMIT peak;
```

**Instrumentation emitted** (PRD FR-080): `rubric.edited`, `submission.scored{elapsed}`,
`decision.recorded{time_to_decision}`, `decisions.exported`.

---

## 3. UJ-2 — Applicant applies `[P1a]` (mobile-web, Arabic/RTL; branches + states)

```mermaid
flowchart TD
  LAND([Public cohort landing<br/>unauth · application.viewed]) --> OPEN{Cohort open?}
  OPEN -->|closed| CLOSED[/"This cohort closed on {date}."<br/>422 · show other open cohorts/]
  OPEN -->|open| APPLY[Tap Apply]
  APPLY --> AUTH[Authenticate via sub]
  AUTH -->|fail| AERR[/"Couldn't sign you in — try again"/]
  AUTH -->|ok| DUP{Already applied?}
  DUP -->|yes| STATUS[Status screen<br/>"You've already applied" · FR-032]
  DUP -->|no| FORM[Stepped form<br/>Next/Back · progress · per-section autosave]

  FORM -->|abandon| AB[(application.abandoned step → C3)]
  FORM --> REVIEW[Final step]
  REVIEW --> CONFIRM{Confirm:<br/>"You can't edit after submitting"}
  CONFIRM -->|cancel| FORM
  CONFIRM -->|submit once| SNAP[Capture immutable snapshot<br/>idempotent · FR-031/032]
  SNAP --> STATUS

  classDef peak fill:#fde68a,stroke:#b45309;
  class SNAP peak;
```

**Cross-tenant / missing** → neutral 404 "Not found or you don't have access" (`FR-004`,
never reveal another tenant). **Every interpolated value** wrapped `bdi` for RTL correctness.

---

## 4. Identity, tenancy & onboarding `[P1a]`

```mermaid
flowchart LR
  V([First-run user]) --> O[App → IdP redirect]
  O --> I[Startup Gate OIDC]
  I -->|return deep-link| R{Has org?}
  R -->|no| C[Create-org form<br/>not skippable]
  C --> ADMIN[Creator = admin<br/>organization_id minted]
  R -->|yes| DL[Land on tenant-scoped deep link / Home]
  ADMIN --> DL

  DL -.->|every query| TI[[Tenant isolation enforced<br/>organization_id on every record]]
```

Subdomains / custom domains + branding are **`[P3]`** (`build-specs 66/67`): host →
tenant resolution rejects unknown hosts; custom domains need ownership verification + TLS.

---

## 5. SaaS commercial plane — subscription, billing, entitlements

Entitlement is **allow-all in P1a** (socket only); counters land P1b; Geidea billing P1b+.

```mermaid
flowchart TD
  subgraph Entitlement[Entitlement & limits]
    ACT[Operator create action] --> CHK{EntitlementService<br/>check — never plan name}
    CHK -->|under limit| OK[Proceed]
    CHK -->|approaching| WARN[/warning banner/]
    CHK -->|reached| BLK[/Block create · FR-062<br/>reads + exports stay live<br/>existing data untouched/]
  end

  subgraph Subscription[Subscription lifecycle P1b]
    PLAN[Pick versioned plan] --> SUBSCR[Subscribe]
    SUBSCR --> UPG{Upgrade / downgrade / add-on}
    UPG --> PRORATE[Re-entitle · prorate]
    SUBSCR --> RENEW[Renew] --> SUBSCR
    SUBSCR --> CANCEL[Cancel → read-only grace]
  end

  subgraph Payments[Geidea payments P1b]
    INIT[Init checkout] --> REDIR[Browser pay redirect]
    REDIR --> RET[/Browser return<br/>NOT authoritative/]
    GW[Geidea] -.verified idempotent callback.-> CB[Process callback<br/>activate entitlement]
    INIT --> GW
    CB --> SUBSCR
  end

  OK -.usage metering.-> Entitlement
```

> Plans versioned/immutable after publish · usage enforced server-side · no raw card/CVV ·
> callbacks verified + idempotent · browser return never authoritative
> (`CLAUDE.md` SaaS rules; `build-specs 59/60/61/62`).

---

## 6. Delivery flows (post-selection) `[P2]`+

```mermaid
flowchart LR
  subgraph Mentorship[Mentorship 14]
    M1[Match mentor↔startup] --> M2[Sessions] --> M3[Track progress / notes]
  end
  subgraph Training[Training 15]
    T1[Curriculum] --> T2[Attendance] --> T3[Progress / completion]
  end
  subgraph Final[Final Evaluation 16 → Graduation 17]
    F1[Final scoring] --> F2{Decision}
    F2 -->|graduate| F3[Graduation record + certificate]
    F2 -->|not yet| F4[Remediation / next cohort]
    F3 --> F5[Alumni follow-up + outcomes/impact]
  end

  M3 --> T1
  T3 --> F1
```

Adjacent capability flows (each `docs/product/features/*`, mostly `[P2]`–`[P4]`):
hackathons/challenges, surveys, service marketplace & requests, risk-intervention,
formal documents, bulk operations/data-quality, outcomes & impact, interviews/public programs.

---

## 7. Cross-cutting flow: published-artifact immutability & audit `[P1a]`

```mermaid
flowchart LR
  DRAFT[Draft form/rubric/stage] --> PUB{Publish}
  PUB --> VER[Version frozen · immutable]
  VER --> USE[Used by cohort]
  EDIT[Need change] --> NEWVER[New version<br/>old snapshots preserved]
  USE --> NEWVER
  USE -.every state change.-> AUD[(Audit log · actor sub · FR-052)]
  EDIT -.-> AUD
```

---

### Coverage note (what's diagrammed vs deferred)

Diagrammed: system map, full lifecycle, both P1a journeys (UJ-1/UJ-2) with branches+states,
identity/tenancy, full SaaS plane (entitlement/subscription/payments), delivery (mentorship/
training/final/graduation/alumni), immutability/audit. Per-feature internal flows
(`docs/product/features/*`, P2–P4) are listed in §6, not expanded — add when each is specced.
