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
  http.post('*/api/v1/forms/:id/fork', ({ params }) => {
    const f = forms.find((x) => x.id === params.id)
    if (!f) return new HttpResponse(null, { status: 404 })
    const from = formVersions.find((v) => v.id === f.published_version_ids[f.published_version_ids.length - 1])
    const vid = `fv_${versionSeq++}`
    const next = f.latest_version + 1
    formVersions.push({ id: vid, form_id: f.id, version: next, status: 'draft', fields: from ? JSON.parse(JSON.stringify(from.fields)) : [], created_at: new Date().toISOString(), published_at: null })
    f.latest_version = next
    f.current_draft_version_id = vid
    return HttpResponse.json({ data: formVersions[formVersions.length - 1] })
  }),
]

export const handlers = [
  ...formHandlers,
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
