import { http, HttpResponse } from 'msw'
import type { SessionUser } from '@/schemas/session'
import type { Organization } from '@/schemas/organizations'
import type { Program } from '@/schemas/programs'
import type { Cohort } from '@/schemas/cohorts'
import type { Role } from '@/schemas/roles'

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

export const handlers = [
  http.get('*/api/v1/auth/session', () => HttpResponse.json({ user })),
  http.get('*/api/v1/organizations', () => HttpResponse.json({ data: [org] })),
  http.get('*/api/v1/programs', () => HttpResponse.json({ data: programs })),
  http.get('*/api/v1/cohorts', () => HttpResponse.json({ data: cohorts })),
  http.get('*/api/v1/me/roles', () => HttpResponse.json({ data: roles })),
]
