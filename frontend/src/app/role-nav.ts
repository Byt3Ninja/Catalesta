import type { RoleKey } from '../schemas/roles'

export interface NavItem {
  label: string
  href: string
}

// Built routes today: Home ('/') and Programs ('/programs'). Everything else
// routes to the ComingSoonPage via /preview/<slug> until its slice lands.
export const ROLE_NAV: Record<RoleKey, NavItem[]> = {
  program_manager: [
    { label: 'Overview', href: '/' },
    { label: 'Programs', href: '/programs' },
    { label: 'Applicants', href: '/preview/applicants' },
    { label: 'Selection', href: '/preview/selection' },
    { label: 'Participants', href: '/preview/participants' },
    { label: 'Program Delivery', href: '/preview/delivery' },
    { label: 'Mentors & Trainers', href: '/preview/mentors-trainers' },
    { label: 'Final Evaluation', href: '/preview/final-evaluation' },
    { label: 'Reports', href: '/preview/reports' },
    { label: 'Configuration', href: '/preview/configuration' },
  ],
  founder: [
    { label: 'Overview', href: '/' },
    { label: 'My Application', href: '/preview/my-application' },
    { label: 'Required Actions', href: '/preview/required-actions' },
    { label: 'Program Journey', href: '/preview/program-journey' },
    { label: 'Sessions', href: '/preview/sessions' },
    { label: 'Training', href: '/preview/training' },
    { label: 'Documents', href: '/preview/documents' },
    { label: 'Messages', href: '/preview/messages' },
    { label: 'My Startup', href: '/preview/my-startup' },
  ],
  co_founder: [
    { label: 'Overview', href: '/' },
    { label: 'My Application', href: '/preview/my-application' },
    { label: 'Program Journey', href: '/preview/program-journey' },
    { label: 'Sessions', href: '/preview/sessions' },
    { label: 'Documents', href: '/preview/documents' },
    { label: 'My Startup', href: '/preview/my-startup' },
  ],
  mentor: [
    { label: 'Overview', href: '/' },
    { label: 'My Mentees', href: '/preview/mentees' },
    { label: 'Sessions', href: '/preview/sessions' },
    { label: 'Availability', href: '/preview/availability' },
    { label: 'Messages', href: '/preview/messages' },
  ],
  trainer: [
    { label: 'Overview', href: '/' },
    { label: 'My Sessions', href: '/preview/training-sessions' },
    { label: 'Attendance', href: '/preview/attendance' },
    { label: 'Materials', href: '/preview/materials' },
  ],
  evaluator: [
    { label: 'Overview', href: '/' },
    { label: 'My Queue', href: '/preview/evaluation-queue' },
    { label: 'Conflicts', href: '/preview/conflicts' },
  ],
  judge: [
    { label: 'Overview', href: '/' },
    { label: 'Panel', href: '/preview/panel' },
    { label: 'Scoring', href: '/preview/final-scoring' },
  ],
  service_provider: [
    { label: 'Overview', href: '/' },
    { label: 'My Offerings', href: '/preview/offerings' },
    { label: 'Requests', href: '/preview/service-requests' },
  ],
  program_coordinator: [
    { label: 'Overview', href: '/' },
    { label: 'Logistics', href: '/preview/logistics' },
    { label: 'Tasks', href: '/preview/coordinator-tasks' },
  ],
  org_admin: [
    { label: 'Overview', href: '/' },
    { label: 'Members', href: '/preview/members' },
    { label: 'Roles', href: '/preview/roles' },
    { label: 'Settings', href: '/preview/org-settings' },
  ],
}
