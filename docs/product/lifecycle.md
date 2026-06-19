# Core Participant Lifecycle

> Owner: Product · Last-updated: 2026-06-19 · Source-of-truth: docs/product/scope-register.md (scope), docs/plan/roadmap.md (sequence)

The configurable core lifecycle every program runs. All stages are configurable
templates (build-spec `06`, Stages module). Each stage links to the module that
owns it in `scope-register.md`.

`Application → Eligibility → Initial Evaluation → Mentorship → Training → Final
Evaluation → Graduation → Alumni Follow-Up`

| Stage | What happens | Owning module |
|---|---|---|
| Application | Applicant submits via a published form; submission captured as an immutable snapshot | Applications (`08`) |
| Eligibility | Declarative eligibility rules screen applicants | Workflows / RoleAssignments (`11`, `12`) |
| Initial Evaluation | Evaluators score against versioned rubrics (decimal) | Assessments (`10`) |
| Mentorship | Mentor matching, sessions, tracking | Mentorship (`14`) |
| Training | Curricula, attendance, progress | Training (`15`) |
| Final Evaluation | End-of-program scoring feeding graduation | FinalEvaluation (`16`) |
| Graduation | Graduation decisions and records | Graduation (`17`) |
| Alumni Follow-Up | Alumni status and post-graduation follow-up | Graduation (`17`) |

Stage transitions, entry/exit gates, and applicability (incl. personalized
tracks — see `features/personalized-tracks.md`) are driven by the Stage engine
and its expression-rule kernel.
