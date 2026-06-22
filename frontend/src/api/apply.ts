import { API_BASE_URL } from './client'
import { csrfFetch } from './csrf'
import {
  applyFormSchema,
  receiptSchema,
  SubmitError,
  type ApplyForm,
  type Receipt,
  type SubmitErrorCode,
} from '../schemas/apply'

/**
 * Best-effort `started` telemetry beacon (FR-080). Fired once when the applicant
 * enters their first answer. Public, no-auth, fire-and-forget — a failed beacon
 * must never affect the applicant's form, so all errors are swallowed.
 */
export async function recordStarted(cohortId: string): Promise<void> {
  try {
    await fetch(`${API_BASE_URL}/apply/${encodeURIComponent(cohortId)}/events`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event: 'started' }),
    })
  } catch {
    // best-effort: telemetry loss is acceptable (the funnel clamps viewed ≥ started)
  }
}

/** Public, no-auth fetch of the cohort's apply form definition. */
export async function fetchApplyForm(cohortId: string): Promise<ApplyForm> {
  const response = await fetch(`${API_BASE_URL}/apply/${encodeURIComponent(cohortId)}`)
  if (!response.ok) {
    throw new Error(`apply-form fetch failed: ${response.status}`)
  }
  const json: unknown = await response.json()
  return applyFormSchema.parse(json)
}

export interface SubmitArgs {
  answers: Record<string, unknown>
  files: File[]
  idempotencyKey: string
}

const ERROR_CODES = new Set<SubmitErrorCode>([
  'COHORT_CLOSED',
  'IDEMPOTENCY_CONFLICT',
  'IDEMPOTENCY_IN_FLIGHT',
  'VALIDATION_ERROR',
])

function toSubmitErrorCode(raw: unknown): SubmitErrorCode {
  return typeof raw === 'string' && ERROR_CODES.has(raw as SubmitErrorCode)
    ? (raw as SubmitErrorCode)
    : 'UNKNOWN'
}

/**
 * Authenticated, idempotent submit. The same Idempotency-Key dedups a retry to
 * the same receipt (server-side). When files are present we must send multipart
 * FormData and let the browser set the boundary Content-Type itself.
 */
export async function submitApplication(
  cohortId: string,
  { answers, files, idempotencyKey }: SubmitArgs,
): Promise<Receipt> {
  const path = `/apply/${encodeURIComponent(cohortId)}/submit`
  const headers: Record<string, string> = { 'Idempotency-Key': idempotencyKey }

  let body: BodyInit
  if (files.length > 0) {
    const form = new FormData()
    for (const [key, value] of Object.entries(answers)) {
      form.append(`answers[${key}]`, serializeAnswer(value))
    }
    for (const file of files) {
      form.append('files[]', file)
    }
    body = form
    // FormData: csrfFetch skips its JSON default so the browser sets the multipart boundary.
  } else {
    body = JSON.stringify({ answers, blob_digests: [] })
    // JSON: csrfFetch defaults Content-Type to application/json.
  }

  const response = await csrfFetch(path, {
    method: 'POST',
    headers,
    body,
  })

  if (response.status === 201) {
    const json: unknown = await response.json()
    return receiptSchema.parse(json)
  }
  if (response.status === 401) {
    throw new SubmitError('UNAUTHENTICATED')
  }

  // 409 / 422 carry { error: { code } }.
  let code: SubmitErrorCode
  try {
    const json = (await response.json()) as { error?: { code?: unknown } }
    code = toSubmitErrorCode(json?.error?.code)
  } catch {
    code = 'UNKNOWN'
  }
  throw new SubmitError(code)
}

function serializeAnswer(value: unknown): string {
  if (value == null) return ''
  if (Array.isArray(value)) return JSON.stringify(value)
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
