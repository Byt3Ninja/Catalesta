import { http, HttpResponse } from 'msw'
import type { SessionUser } from '@/schemas/session'
import type { Organization } from '@/schemas/organizations'
import type { Program } from '@/schemas/programs'
import type { Cohort } from '@/schemas/cohorts'
import type { Role } from '@/schemas/roles'
import type { ActionItem } from '@/schemas/actionCenter'
import type { RoleKey } from '@/schemas/roles'
import type { Notification } from '@/schemas/notifications'
import type { SearchGroup } from '@/schemas/search'
import type { ConsentCategory } from '@/schemas/consent'

const NOW = '2026-06-01T00:00:00Z'

// Mock consent state — `profile` starts NOT granted so the consent gate is
// demonstrable (profile read 403s until granted on the consent screen).
const CONSENT_STATE: Record<ConsentCategory, boolean> = { profile: false, contact: false, documents: false }

const PROFILE = { display_name: 'Alice', email: 'alice@catalesta.test', organization: 'Acme Incubator', title: 'Founder' }

const user: SessionUser = {
  id: 'acc_demo',
  email: 'alice@catalesta.test',
  display_name: 'Alice',
  email_verified: true,
  startup_gate_subject_id: null,
  linked_providers: [],
  has_password: true,
}

const org: Organization = {
  id: 'org_demo',
  name: 'Acme Incubator',
  slug: 'acme-incubator',
  branding: null,
  created_at: NOW,
  updated_at: NOW,
}

const programs: Program[] = [
  {
    id: 'prog_1',
    name: 'FinTech Accelerator 2026',
    slug: 'fintech-2026',
    status: 'published',
    description: 'Spring cohort intake.',
    settings: null,
    created_at: NOW,
    updated_at: NOW,
  },
  {
    id: 'prog_2',
    name: 'HealthTech (draft)',
    slug: 'healthtech',
    status: 'draft',
    description: null,
    settings: null,
    created_at: NOW,
    updated_at: NOW,
  },
]

const roles: Role[] = [
  { key: 'program_manager', label: 'Program Manager' },
  { key: 'founder', label: 'Founder' },
  { key: 'mentor', label: 'Mentor' },
  { key: 'evaluator', label: 'Evaluator' },
]

const cohorts: Cohort[] = [
  {
    id: 'coh_1',
    organization_id: 'org_demo',
    program_id: 'prog_1',
    name: 'Spring 2026',
    slug: 'spring-2026',
    status: 'open',
    capacity: 20,
    enrollment_opens_at: NOW,
    enrollment_closes_at: null,
    starts_at: null,
    ends_at: null,
    timeline: null,
    submissions_count: 3,
    created_at: NOW,
    updated_at: NOW,
  },
]

const ACTION_CENTER: Record<RoleKey, ActionItem[]> = {
  program_manager: [
    { id: 'pm1', section: 'required_actions', what: 'Review 4 delayed applications', why: 'Past the screening SLA', deadline: 'Today', who: 'You', href: '/preview/applicants', blocker: null },
    { id: 'pm2', section: 'required_actions', what: 'Assign evaluators to Spring 2026', why: '12 submissions unassigned', deadline: 'Jun 30', who: 'You', href: '/preview/selection', blocker: null },
    { id: 'pm3', section: 'blocked_items', what: 'Approve stage transition', why: 'Cohort cannot advance', deadline: null, who: 'You', href: '/preview/configuration', blocker: 'Missing evaluator coverage' },
  ],
  founder: [
    { id: 'f1', section: 'required_actions', what: 'Complete the Team section', why: 'Application is 80% done', deadline: 'Jul 2', who: 'You', href: '/preview/my-application', blocker: null },
    { id: 'f2', section: 'required_actions', what: 'Upload the rejected pitch deck', why: 'Reviewer requested a new version', deadline: 'Jul 1', who: 'You', href: '/preview/documents', blocker: null },
    { id: 'f3', section: 'upcoming_sessions', what: 'Confirm mentor session', why: 'With Layla, Thu 3pm', deadline: 'Wed', who: 'You', href: '/preview/sessions', blocker: null },
  ],
  co_founder: [
    { id: 'cf1', section: 'required_actions', what: 'Review the application before submit', why: 'Your co-founder needs sign-off', deadline: 'Jul 2', who: 'You', href: '/preview/my-application', blocker: null },
    { id: 'cf2', section: 'progress', what: 'Startup profile 60% complete', why: 'Add traction metrics', deadline: null, who: 'You', href: '/preview/my-startup', blocker: null },
  ],
  mentor: [
    { id: 'm1', section: 'required_actions', what: 'Accept mentee assignment', why: '2 startups matched to you', deadline: 'Jun 29', who: 'You', href: '/preview/mentees', blocker: null },
    { id: 'm2', section: 'required_actions', what: 'Submit session notes', why: 'Session held yesterday', deadline: 'Today', who: 'You', href: '/preview/sessions', blocker: null },
    { id: 'm3', section: 'upcoming_sessions', what: 'Prepare for Fri session', why: 'Topic: go-to-market', deadline: 'Fri', who: 'You', href: '/preview/sessions', blocker: null },
  ],
  trainer: [
    { id: 't1', section: 'required_actions', what: 'Publish workshop materials', why: 'Session is tomorrow', deadline: 'Tomorrow', who: 'You', href: '/preview/materials', blocker: null },
    { id: 't2', section: 'upcoming_sessions', what: 'Take attendance for Cohort A', why: 'Live training at 2pm', deadline: 'Today', who: 'You', href: '/preview/attendance', blocker: null },
  ],
  evaluator: [
    { id: 'e1', section: 'required_actions', what: 'Score 5 assigned applications', why: 'Scoring window closes soon', deadline: 'Jun 30', who: 'You', href: '/preview/evaluation-queue', blocker: null },
    { id: 'e2', section: 'blocked_items', what: 'Resolve conflict declaration', why: 'Cannot score until cleared', deadline: null, who: 'You', href: '/preview/conflicts', blocker: 'Relationship disclosure pending' },
  ],
  judge: [
    { id: 'j1', section: 'required_actions', what: 'Finalize panel scores', why: 'Decision meeting Friday', deadline: 'Fri', who: 'You', href: '/preview/final-scoring', blocker: null },
    { id: 'j2', section: 'recent_decisions', what: 'Cohort A shortlist published', why: '8 of 24 advanced', deadline: null, who: null, href: '/preview/panel', blocker: null },
  ],
  service_provider: [
    { id: 'sp1', section: 'required_actions', what: 'Respond to 3 service requests', why: 'New requests this week', deadline: 'Jun 30', who: 'You', href: '/preview/service-requests', blocker: null },
    { id: 'sp2', section: 'progress', what: 'Listing views up 20%', why: 'Legal review offering', deadline: null, who: null, href: '/preview/offerings', blocker: null },
  ],
  program_coordinator: [
    { id: 'pc1', section: 'required_actions', what: 'Book venue for demo day', why: 'Date confirmed', deadline: 'Jul 5', who: 'You', href: '/preview/logistics', blocker: null },
    { id: 'pc2', section: 'deadlines', what: 'Send session reminders', why: '3 sessions next week', deadline: 'Mon', who: 'You', href: '/preview/coordinator-tasks', blocker: null },
  ],
  org_admin: [
    { id: 'oa1', section: 'required_actions', what: 'Invite 2 pending evaluators', why: 'Selection needs coverage', deadline: 'Jun 29', who: 'You', href: '/preview/members', blocker: null },
    { id: 'oa2', section: 'opportunities', what: 'Review role permissions', why: 'New coordinator added', deadline: null, who: 'You', href: '/preview/roles', blocker: null },
  ],
}

// Module-level mock state: notification read-status mutates within a session.
const NOTIFICATIONS: Notification[] = [
  { id: 'n1', type: 'action', title: 'Review delayed applications', body: '4 applications are past the screening SLA.', created_at: '2026-06-26T09:00:00Z', read_at: null, href: '/preview/applicants' },
  { id: 'n2', type: 'message', title: 'New message from Layla', body: 'Confirming Thursday 3pm mentor session.', created_at: '2026-06-25T14:30:00Z', read_at: null, href: '/preview/sessions' },
  { id: 'n3', type: 'system', title: 'Cohort Spring 2026 opened', body: 'Enrollment is now open.', created_at: '2026-06-24T08:00:00Z', read_at: '2026-06-24T10:00:00Z', href: null },
]

const SEARCH_INDEX: SearchGroup[] = [
  { category: 'people', items: [
    { id: 'p1', label: 'Alice Founder', sublabel: 'Founder · Acme', href: '/preview/people/p1' },
    { id: 'p2', label: 'Layla Mentor', sublabel: 'Mentor', href: '/preview/people/p2' },
  ] },
  { category: 'programs', items: [
    { id: 'prog_1', label: 'FinTech Accelerator 2026', sublabel: 'Published', href: '/programs/prog_1' },
  ] },
  { category: 'cohorts', items: [
    { id: 'coh_1', label: 'Spring 2026', sublabel: 'Open', href: '/cohorts/coh_1' },
  ] },
  { category: 'documents', items: [
    { id: 'd1', label: 'Pitch deck v2', sublabel: 'PDF', href: '/preview/documents/d1' },
  ] },
]

// ---- forms store ----
type FormRec = { id: string; name: string; description: string | null; latest_version: number; published_version_ids: string[]; current_draft_version_id: string | null }
type FormVersionRec = { id: string; form_id: string; version: number; status: 'draft' | 'published'; fields: unknown[]; created_at: string; published_at: string | null }

const forms: FormRec[] = [
  { id: 'frm_pub', name: 'Application form', description: 'Main intake', latest_version: 2, published_version_ids: ['fv_pub_1'], current_draft_version_id: 'fv_pub_2' },
  { id: 'frm_draft', name: 'New form', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_draft_1' },
]
const formVersions: FormVersionRec[] = [
  { id: 'fv_pub_1', form_id: 'frm_pub', version: 1, status: 'published', fields: [
    { id: 'f_name', type: 'short_text', label: 'Startup name', required: true },
    { id: 'f_stage', type: 'single_select', label: 'Stage', options: ['Idea', 'MVP'] },
  ], created_at: NOW, published_at: NOW },
  { id: 'fv_pub_2', form_id: 'frm_pub', version: 2, status: 'draft', fields: [
    { id: 'f_name', type: 'short_text', label: 'Startup name', required: true },
    { id: 'f_stage', type: 'single_select', label: 'Stage', options: ['Idea', 'MVP', 'Growth'] },
  ], created_at: NOW, published_at: null },
  { id: 'fv_draft_1', form_id: 'frm_draft', version: 1, status: 'draft', fields: [], created_at: NOW, published_at: null },
]
let formSeq = 3
let versionSeq = 3

const formHandlers = [
  http.get('*/api/v1/forms', () => HttpResponse.json({ data: forms })),
  http.post('*/api/v1/forms', async ({ request }) => {
    const body = (await request.json()) as { name?: string }
    const name = (body.name ?? '').trim()
    if (!name) return HttpResponse.json({ error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } }, { status: 422 })
    const fid = `frm_${formSeq++}`, vid = `fv_${versionSeq++}`
    formVersions.push({ id: vid, form_id: fid, version: 1, status: 'draft', fields: [], created_at: new Date().toISOString(), published_at: null })
    const rec: FormRec = { id: fid, name, description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: vid }
    forms.push(rec)
    return HttpResponse.json({ data: rec }, { status: 201 })
  }),
  http.get('*/api/v1/forms/:id', ({ params }) => {
    const f = forms.find((x) => x.id === params.id)
    return f ? HttpResponse.json({ data: f }) : new HttpResponse(null, { status: 404 })
  }),
  http.get('*/api/v1/forms/:id/versions', ({ params }) => HttpResponse.json({ data: formVersions.filter((v) => v.form_id === params.id).sort((a, b) => b.version - a.version) })),
  http.get('*/api/v1/form-versions/:versionId', ({ params }) => {
    const v = formVersions.find((x) => x.id === params.versionId)
    return v ? HttpResponse.json({ data: v }) : new HttpResponse(null, { status: 404 })
  }),
  http.patch('*/api/v1/forms/:id/draft', async ({ params, request }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f || !f.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = formVersions.find((v) => v.id === f.current_draft_version_id)
    if (!draft) return new HttpResponse(null, { status: 404 })
    if (draft.status === 'published') return new HttpResponse(null, { status: 409 })
    const body = (await request.json()) as { fields?: unknown[] }
    draft.fields = body.fields ?? []
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/forms/:id/publish', ({ params }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f || !f.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = formVersions.find((v) => v.id === f.current_draft_version_id)
    if (!draft || draft.status === 'published') return new HttpResponse(null, { status: 409 })
    draft.status = 'published'
    draft.published_at = new Date().toISOString()
    f.published_version_ids.push(draft.id)
    f.current_draft_version_id = null
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/forms/:id/fork', async ({ params, request }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f) return new HttpResponse(null, { status: 404 })
    // Read from_version_id from the request body; fall back to latest published if omitted.
    let fromVersionId: string | undefined
    try {
      const body = (await request.json()) as { from_version_id?: string }
      fromVersionId = body.from_version_id
    } catch {
      // body may be absent or non-JSON
    }
    const from = fromVersionId
      ? formVersions.find((v) => v.id === fromVersionId)
      : formVersions.find((v) => v.id === f.published_version_ids[f.published_version_ids.length - 1])
    const vid = `fv_${versionSeq++}`
    const next = f.latest_version + 1
    formVersions.push({ id: vid, form_id: f.id, version: next, status: 'draft', fields: from ? JSON.parse(JSON.stringify(from.fields)) : [], created_at: new Date().toISOString(), published_at: null })
    f.latest_version = next
    f.current_draft_version_id = vid
    return HttpResponse.json({ data: formVersions[formVersions.length - 1] })
  }),
]

// ---- stage pipelines store ----
type StageRec = { stage_id: string; name: string; type: string; entry_rule: unknown; exit_rule: unknown; next_stage_ids: string[]; depends_on_stage_ids: string[]; parallel_group: string | null; order: number }
type PipelineRec = { pipeline_id: string; program_id: string; name: string; latest_version: number; published_version_ids: string[]; current_draft_version_id: string | null; created_at: string }
type PipelineVersionRec = { version_id: string; pipeline_id: string; version: number; status: 'draft' | 'published'; stages: StageRec[]; created_at: string; published_at: string | null }

const stageTemplates = [
  { template_id: 'tpl_review', name: 'Review', type: 'review' },
  { template_id: 'tpl_interview', name: 'Interview', type: 'interview' },
  { template_id: 'tpl_task', name: 'Task', type: 'task' },
  { template_id: 'tpl_decision', name: 'Decision', type: 'decision' },
  { template_id: 'tpl_automated', name: 'Automated', type: 'automated' },
]

const pipelines: PipelineRec[] = [
  { pipeline_id: 'pl_pub', program_id: 'prog_1', name: 'Acceleration pipeline', latest_version: 2, published_version_ids: ['plv_pub_1'], current_draft_version_id: 'plv_pub_2', created_at: NOW },
  { pipeline_id: 'pl_draft', program_id: 'prog_1', name: 'New pipeline', latest_version: 1, published_version_ids: [], current_draft_version_id: 'plv_draft_1', created_at: NOW },
]
const pipelineVersions: PipelineVersionRec[] = [
  { version_id: 'plv_pub_1', pipeline_id: 'pl_pub', version: 1, status: 'published', stages: [
    { stage_id: 's_screen', name: 'Screening', type: 'review', entry_rule: null, exit_rule: { match: 'all', conditions: [{ field_id: 'score', operator: 'not_equals', value: '' }] }, next_stage_ids: ['s_interview'], depends_on_stage_ids: [], parallel_group: null, order: 0 },
    { stage_id: 's_interview', name: 'Interview', type: 'interview', entry_rule: null, exit_rule: null, next_stage_ids: ['s_decide'], depends_on_stage_ids: [], parallel_group: null, order: 1 },
    { stage_id: 's_decide', name: 'Decision', type: 'decision', entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, order: 2 },
  ], created_at: NOW, published_at: NOW },
  { version_id: 'plv_pub_2', pipeline_id: 'pl_pub', version: 2, status: 'draft', stages: [
    { stage_id: 's_screen', name: 'Screening', type: 'review', entry_rule: null, exit_rule: null, next_stage_ids: ['s_interview'], depends_on_stage_ids: [], parallel_group: null, order: 0 },
    { stage_id: 's_interview', name: 'Interview', type: 'interview', entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, order: 1 },
  ], created_at: NOW, published_at: null },
  { version_id: 'plv_draft_1', pipeline_id: 'pl_draft', version: 1, status: 'draft', stages: [], created_at: NOW, published_at: null },
]
let pipelineSeq = 3
let pipelineVersionSeq = 3

const stagePipelineHandlers = [
  http.get('*/api/v1/stage-templates', () => HttpResponse.json({ data: stageTemplates })),
  http.get('*/api/v1/programs/:programId/stage-pipelines', ({ params }) =>
    HttpResponse.json({ data: pipelines.filter((p) => p.program_id === params.programId) })),
  http.post('*/api/v1/programs/:programId/stage-pipelines', async ({ params, request }) => {
    const body = (await request.json()) as { name?: string }
    const name = (body.name ?? '').trim()
    if (!name) return HttpResponse.json({ error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } }, { status: 422 })
    const pid = `pl_${pipelineSeq++}`, vid = `plv_${pipelineVersionSeq++}`
    pipelineVersions.push({ version_id: vid, pipeline_id: pid, version: 1, status: 'draft', stages: [], created_at: new Date().toISOString(), published_at: null })
    const rec: PipelineRec = { pipeline_id: pid, program_id: String(params.programId), name, latest_version: 1, published_version_ids: [], current_draft_version_id: vid, created_at: new Date().toISOString() }
    pipelines.push(rec)
    return HttpResponse.json({ data: rec }, { status: 201 })
  }),
  http.get('*/api/v1/stage-pipelines/:id', ({ params }) => {
    const p = pipelines.find((x) => x.pipeline_id === params.id)
    return p ? HttpResponse.json({ data: p }) : new HttpResponse(null, { status: 404 })
  }),
  http.get('*/api/v1/stage-pipelines/:id/versions', ({ params }) =>
    HttpResponse.json({ data: pipelineVersions.filter((v) => v.pipeline_id === params.id).sort((a, b) => b.version - a.version) })),
  http.get('*/api/v1/stage-pipeline-versions/:versionId', ({ params }) => {
    const v = pipelineVersions.find((x) => x.version_id === params.versionId)
    return v ? HttpResponse.json({ data: v }) : new HttpResponse(null, { status: 404 })
  }),
  http.patch('*/api/v1/stage-pipelines/:id/draft', async ({ params, request }) => {
    const p = pipelines.find((x) => x.pipeline_id === params.id)
    if (!p || !p.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = pipelineVersions.find((v) => v.version_id === p.current_draft_version_id)
    if (!draft) return new HttpResponse(null, { status: 404 })
    if (draft.status === 'published') return new HttpResponse(null, { status: 409 })
    const body = (await request.json()) as { stages?: StageRec[] }
    draft.stages = body.stages ?? []
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/stage-pipelines/:id/publish', ({ params }) => {
    const p = pipelines.find((x) => x.pipeline_id === params.id)
    if (!p || !p.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = pipelineVersions.find((v) => v.version_id === p.current_draft_version_id)
    if (!draft || draft.status === 'published') return new HttpResponse(null, { status: 409 })
    draft.status = 'published'
    draft.published_at = new Date().toISOString()
    p.published_version_ids.push(draft.version_id)
    p.current_draft_version_id = null
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/stage-pipelines/:id/fork', async ({ params, request }) => {
    const p = pipelines.find((x) => x.pipeline_id === params.id)
    if (!p) return new HttpResponse(null, { status: 404 })
    let fromVersionId: string | undefined
    try {
      const body = (await request.json()) as { from_version_id?: string }
      fromVersionId = body.from_version_id
    } catch {
      // body may be absent or non-JSON
    }
    const from = fromVersionId
      ? pipelineVersions.find((v) => v.version_id === fromVersionId)
      : pipelineVersions.find((v) => v.version_id === p.published_version_ids[p.published_version_ids.length - 1])
    const vid = `plv_${pipelineVersionSeq++}`
    const next = p.latest_version + 1
    pipelineVersions.push({ version_id: vid, pipeline_id: p.pipeline_id, version: next, status: 'draft', stages: from ? JSON.parse(JSON.stringify(from.stages)) : [], created_at: new Date().toISOString(), published_at: null })
    p.latest_version = next
    p.current_draft_version_id = vid
    return HttpResponse.json({ data: pipelineVersions[pipelineVersions.length - 1] })
  }),
]

// ---- scoring models store ----
type ScoringCriterionRec = { criterion_id: string; label: string; max_points: number; descriptors: string[] | null }
type ScoringModelRec = { model_id: string; program_id: string; name: string; latest_version: number; published_version_ids: string[]; current_draft_version_id: string | null; created_at: string }
type ScoringModelVersionRec = { version_id: string; model_id: string; version: number; status: 'draft' | 'published'; criteria: ScoringCriterionRec[]; created_at: string; published_at: string | null }

const scoringModels: ScoringModelRec[] = [
  { model_id: 'sm_pub', program_id: 'prog_1', name: 'Technical Assessment', latest_version: 1, published_version_ids: ['smv_pub_1'], current_draft_version_id: null, created_at: NOW },
  { model_id: 'sm_draft', program_id: 'prog_1', name: 'Market Fit Assessment', latest_version: 1, published_version_ids: [], current_draft_version_id: 'smv_draft_1', created_at: NOW },
]
const scoringModelVersions: ScoringModelVersionRec[] = [
  { version_id: 'smv_pub_1', model_id: 'sm_pub', version: 1, status: 'published', criteria: [
    { criterion_id: 'c_innovation', label: 'Innovation', max_points: 10, descriptors: ['Highly innovative', 'Somewhat innovative', 'Not innovative'] },
    { criterion_id: 'c_market', label: 'Market Opportunity', max_points: 10, descriptors: ['Large market', 'Medium market', 'Small market'] },
    { criterion_id: 'c_team', label: 'Team Strength', max_points: 10, descriptors: ['Strong team', 'Adequate team', 'Weak team'] },
  ], created_at: NOW, published_at: NOW },
  { version_id: 'smv_draft_1', model_id: 'sm_draft', version: 1, status: 'draft', criteria: [], created_at: NOW, published_at: null },
]
// Empty stores consumed by future tasks (Tasks 8, 9, 11)
type AssignmentRec = { assignment_id: string; cohort_id: string; stage_id: string; application_id: string; reviewer_id: string; status: 'pending' | 'submitted' }
type ScorecardRec = { scorecard_id: string; cohort_id: string; stage_id: string; application_id: string; reviewer_id: string; model_version_id: string; values: Record<string, number>; disqualified: boolean; status: 'draft' | 'submitted'; submitted_at: string | null }
type DecisionRec = { decision_id: string; cohort_id: string; stage_id: string; application_id: string; outcome: string; snapshot: unknown; decided_by: string }
const assignments: AssignmentRec[] = []
const scorecards: ScorecardRec[] = []
const decisions: DecisionRec[] = []
let scoringModelSeq = 3
let scoringModelVersionSeq = 3

const scoringModelHandlers = [
  http.get('*/api/v1/programs/:programId/scoring-models', ({ params }) =>
    HttpResponse.json({ data: scoringModels.filter((m) => m.program_id === params.programId) })),
  http.post('*/api/v1/programs/:programId/scoring-models', async ({ params, request }) => {
    const body = (await request.json()) as { name?: string }
    const name = (body.name ?? '').trim()
    if (!name) return HttpResponse.json({ error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } }, { status: 422 })
    const mid = `sm_${scoringModelSeq++}`, vid = `smv_${scoringModelVersionSeq++}`
    scoringModelVersions.push({ version_id: vid, model_id: mid, version: 1, status: 'draft', criteria: [], created_at: new Date().toISOString(), published_at: null })
    const rec: ScoringModelRec = { model_id: mid, program_id: String(params.programId), name, latest_version: 1, published_version_ids: [], current_draft_version_id: vid, created_at: new Date().toISOString() }
    scoringModels.push(rec)
    return HttpResponse.json({ data: rec }, { status: 201 })
  }),
  http.get('*/api/v1/scoring-models/:id', ({ params }) => {
    const m = scoringModels.find((x) => x.model_id === params.id)
    return m ? HttpResponse.json({ data: m }) : new HttpResponse(null, { status: 404 })
  }),
  http.get('*/api/v1/scoring-models/:id/versions', ({ params }) =>
    HttpResponse.json({ data: scoringModelVersions.filter((v) => v.model_id === params.id).sort((a, b) => b.version - a.version) })),
  http.get('*/api/v1/scoring-model-versions/:versionId', ({ params }) => {
    const v = scoringModelVersions.find((x) => x.version_id === params.versionId)
    return v ? HttpResponse.json({ data: v }) : new HttpResponse(null, { status: 404 })
  }),
  http.patch('*/api/v1/scoring-models/:id/draft', async ({ params, request }) => {
    const m = scoringModels.find((x) => x.model_id === params.id)
    if (!m || !m.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = scoringModelVersions.find((v) => v.version_id === m.current_draft_version_id)
    if (!draft) return new HttpResponse(null, { status: 404 })
    if (draft.status === 'published') return new HttpResponse(null, { status: 409 })
    const body = (await request.json()) as { criteria?: ScoringCriterionRec[] }
    draft.criteria = body.criteria ?? []
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/scoring-models/:id/publish', ({ params }) => {
    const m = scoringModels.find((x) => x.model_id === params.id)
    if (!m || !m.current_draft_version_id) return new HttpResponse(null, { status: 404 })
    const draft = scoringModelVersions.find((v) => v.version_id === m.current_draft_version_id)
    if (!draft || draft.status === 'published') return new HttpResponse(null, { status: 409 })
    draft.status = 'published'
    draft.published_at = new Date().toISOString()
    m.published_version_ids.push(draft.version_id)
    m.current_draft_version_id = null
    return HttpResponse.json({ data: draft })
  }),
  http.post('*/api/v1/scoring-models/:id/fork', async ({ params, request }) => {
    const m = scoringModels.find((x) => x.model_id === params.id)
    if (!m) return new HttpResponse(null, { status: 404 })
    let fromVersionId: string | undefined
    try {
      const body = (await request.json()) as { from_version_id?: string }
      fromVersionId = body.from_version_id
    } catch {
      // body may be absent or non-JSON
    }
    const from = fromVersionId
      ? scoringModelVersions.find((v) => v.version_id === fromVersionId)
      : scoringModelVersions.find((v) => v.version_id === m.published_version_ids[m.published_version_ids.length - 1])
    const vid = `smv_${scoringModelVersionSeq++}`
    const next = m.latest_version + 1
    scoringModelVersions.push({ version_id: vid, model_id: m.model_id, version: next, status: 'draft', criteria: from ? JSON.parse(JSON.stringify(from.criteria)) : [], created_at: new Date().toISOString(), published_at: null })
    m.latest_version = next
    m.current_draft_version_id = vid
    return HttpResponse.json({ data: scoringModelVersions[scoringModelVersions.length - 1] })
  }),
  http.get('*/api/v1/cohorts/:cohortId/stages/:stageId/assignments', ({ params }) =>
    HttpResponse.json({ data: assignments.filter((a) => a.cohort_id === params.cohortId && a.stage_id === params.stageId) })),
  http.get('*/api/v1/cohorts/:cohortId/stages/:stageId/scorecards/:applicationId/:reviewerId', ({ params }) => {
    const sc = scorecards.find((s) => s.cohort_id === params.cohortId && s.stage_id === params.stageId && s.application_id === params.applicationId && s.reviewer_id === params.reviewerId)
    return sc ? HttpResponse.json({ data: sc }) : new HttpResponse(null, { status: 404 })
  }),
]

// Silence unused-variable warnings for stores consumed by future tasks.
void decisions

export const handlers = [
  ...formHandlers,
  ...stagePipelineHandlers,
  ...scoringModelHandlers,
  // --- Auth mutations (prototype: always succeed; no real credential check) ---
  // Sanctum CSRF preflight lives at the app root (not under /api/v1).
  http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.post('*/api/v1/auth/register', () => HttpResponse.json({ user }, { status: 201 })),
  http.post('*/api/v1/auth/password/login', () => HttpResponse.json({ user })),
  http.post('*/api/v1/auth/password/forgot', () => new HttpResponse(null, { status: 200 })),
  http.post('*/api/v1/auth/password/reset', () => new HttpResponse(null, { status: 200 })),
  http.post('*/api/v1/auth/email/resend', () => new HttpResponse(null, { status: 204 })),

  http.get('*/api/v1/auth/session', () => HttpResponse.json({ user })),
  http.get('*/api/v1/organizations', () => HttpResponse.json({ data: [org] })),
  http.get('*/api/v1/programs', () => HttpResponse.json({ data: programs })),
  http.get('*/api/v1/cohorts', () => HttpResponse.json({ data: cohorts })),
  http.get('*/api/v1/cohorts/:id', ({ params }) => {
    const found = cohorts.find((c) => c.id === params.id)
    if (!found) return new HttpResponse(null, { status: 404 })
    return HttpResponse.json({ data: found })
  }),
  http.post('*/api/v1/programs/:programId/cohorts', async ({ params, request }) => {
    const body = (await request.json()) as { name?: string }
    const name = (body.name ?? '').trim()
    if (!name) {
      return HttpResponse.json(
        { error: { code: 'VALIDATION_ERROR', details: { name: ['The name field is required.'] } } },
        { status: 422 },
      )
    }
    const now = new Date().toISOString()
    const created = {
      id: `coh_${cohorts.length + 1}`,
      organization_id: 'org_demo',
      program_id: String(params.programId),
      name,
      slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''),
      status: 'draft' as const,
      capacity: null,
      enrollment_opens_at: null,
      enrollment_closes_at: null,
      starts_at: null,
      ends_at: null,
      timeline: null,
      submissions_count: 0,
      created_at: now,
      updated_at: now,
    }
    cohorts.push(created)
    return HttpResponse.json({ data: created }, { status: 201 })
  }),
  http.patch('*/api/v1/cohorts/:id', async ({ params, request }) => {
    const found = cohorts.find((c) => c.id === params.id)
    if (!found) return new HttpResponse(null, { status: 404 })
    const body = (await request.json()) as Record<string, unknown>
    for (const key of ['name', 'capacity', 'enrollment_opens_at', 'enrollment_closes_at', 'starts_at', 'ends_at'] as const) {
      if (key in body) (found as Record<string, unknown>)[key] = body[key]
    }
    found.updated_at = new Date().toISOString()
    return HttpResponse.json({ data: found })
  }),
  http.post('*/api/v1/cohorts/:id/open', ({ params }) => {
    const found = cohorts.find((c) => c.id === params.id)
    if (!found) return new HttpResponse(null, { status: 404 })
    found.status = 'open'
    found.updated_at = new Date().toISOString()
    return HttpResponse.json({ data: found })
  }),
  http.post('*/api/v1/cohorts/:id/bind-form', async ({ params, request }) => {
    const c = cohorts.find((x) => x.id === params.id)
    if (!c) return new HttpResponse(null, { status: 404 })
    const b = (await request.json()) as { form_version_id?: string }
    ;(c as Record<string, unknown>).bound_form_version_id = b.form_version_id ?? null
    c.updated_at = new Date().toISOString()
    return HttpResponse.json({ data: c })
  }),
  http.post('*/api/v1/cohorts/:id/bind-stage-pipeline', async ({ params, request }) => {
    const c = cohorts.find((x) => x.id === params.id)
    if (!c) return new HttpResponse(null, { status: 404 })
    const b = (await request.json()) as { stage_pipeline_version_id?: string }
    ;(c as Record<string, unknown>).stage_pipeline_version_id = b.stage_pipeline_version_id ?? null
    c.updated_at = new Date().toISOString()
    return HttpResponse.json({ data: c })
  }),
  http.post('*/api/v1/cohorts/:id/bind-stage-scoring-model', async ({ params, request }) => {
    const c = cohorts.find((x) => x.id === params.id)
    if (!c) return new HttpResponse(null, { status: 404 })
    const b = (await request.json()) as { stage_id?: string; scoring_model_version_id?: string }
    const stage_id = b.stage_id ?? ''
    const existing = ((c as Record<string, unknown>).stage_scoring_model_version_ids ?? {}) as Record<string, string>
    ;(c as Record<string, unknown>).stage_scoring_model_version_ids = stage_id && b.scoring_model_version_id
      ? { ...existing, [stage_id]: b.scoring_model_version_id }
      : existing
    c.updated_at = new Date().toISOString()
    return HttpResponse.json({ data: c })
  }),
  http.get('*/api/v1/me/roles', () => HttpResponse.json({ data: roles })),
  http.get('*/api/v1/me/action-center', ({ request }) => {
    const role = (new URL(request.url).searchParams.get('role') ?? 'program_manager') as RoleKey
    return HttpResponse.json({ data: ACTION_CENTER[role] ?? [] })
  }),
  http.get('*/api/v1/notifications', () => HttpResponse.json({ data: NOTIFICATIONS })),
  http.post('*/api/v1/notifications/read-all', () => {
    for (const n of NOTIFICATIONS) if (n.read_at === null) n.read_at = NOW
    return new HttpResponse(null, { status: 204 })
  }),
  http.post('*/api/v1/notifications/:id/read', ({ params }) => {
    const found = NOTIFICATIONS.find((n) => n.id === params.id)
    if (found && found.read_at === null) found.read_at = NOW
    return new HttpResponse(null, { status: 204 })
  }),

  http.get('*/api/v1/search', ({ request }) => {
    const q = (new URL(request.url).searchParams.get('q') ?? '').trim().toLowerCase()
    if (q === '') return HttpResponse.json({ data: [] })
    const data = SEARCH_INDEX
      .map((g) => ({ category: g.category, items: g.items.filter((i) => `${i.label} ${i.sublabel ?? ''}`.toLowerCase().includes(q)) }))
      .filter((g) => g.items.length > 0)
    return HttpResponse.json({ data })
  }),

  http.get('*/api/v1/me/profile', () =>
    CONSENT_STATE.profile ? HttpResponse.json(PROFILE) : new HttpResponse('forbidden', { status: 403 }),
  ),
  http.get('*/api/v1/me/consent', () =>
    HttpResponse.json({ data: (Object.keys(CONSENT_STATE) as ConsentCategory[]).map((category) => ({ category, granted: CONSENT_STATE[category] })) }),
  ),
  http.post('*/api/v1/me/consent', async ({ request }) => {
    const body = (await request.json()) as { category: ConsentCategory; granted: boolean }
    CONSENT_STATE[body.category] = body.granted
    return new HttpResponse(null, { status: 204 })
  }),
]
