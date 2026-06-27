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

const NOW = '2026-06-01T00:00:00Z'

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

export const handlers = [
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
]
