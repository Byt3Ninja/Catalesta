import { apiFetch } from './tenant'
import { csrfFetch } from './csrf'
import {
  GetFormError, SaveFormError, PublishFormError,
  formListResponseSchema, formResponseSchema, formVersionResponseSchema, formVersionListResponseSchema,
  type Form, type FormField, type FormVersion,
} from '../schemas/forms'

export { GetFormError, SaveFormError, PublishFormError }

export async function listForms(): Promise<Form[]> {
  const res = await apiFetch('/forms')
  if (res.status !== 200) throw new GetFormError(res.status === 401 ? 'UNAUTHENTICATED' : 'UNKNOWN')
  return formListResponseSchema.parse(await res.json()).data
}

export async function getForm(id: string): Promise<Form> {
  const res = await apiFetch(`/forms/${id}`)
  if (res.status === 200) return formResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetFormError('NOT_FOUND')
  if (res.status === 401) throw new GetFormError('UNAUTHENTICATED')
  throw new GetFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function listFormVersions(formId: string): Promise<FormVersion[]> {
  const res = await apiFetch(`/forms/${formId}/versions`)
  if (res.status !== 200) throw new GetFormError(res.status === 404 ? 'NOT_FOUND' : 'UNKNOWN')
  return formVersionListResponseSchema.parse(await res.json()).data
}

export async function getFormVersion(versionId: string): Promise<FormVersion> {
  const res = await apiFetch(`/form-versions/${versionId}`)
  if (res.status === 200) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetFormError('NOT_FOUND')
  throw new GetFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function createForm(name: string): Promise<Form> {
  const res = await csrfFetch('/forms', { method: 'POST', body: JSON.stringify({ name }) })
  if (res.status === 201) return formResponseSchema.parse(await res.json()).data
  if (res.status === 422) throw new SaveFormError('VALIDATION', 'The form name is required.')
  throw new SaveFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function saveFormDraft(formId: string, fields: FormField[]): Promise<FormVersion> {
  const res = await csrfFetch(`/forms/${formId}/draft`, { method: 'PATCH', body: JSON.stringify({ fields }) })
  if (res.status === 200) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SaveFormError('NOT_FOUND')
  if (res.status === 409) throw new SaveFormError('CONFLICT', 'This version is published and read-only.')
  throw new SaveFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function publishForm(formId: string): Promise<FormVersion> {
  const res = await csrfFetch(`/forms/${formId}/publish`, { method: 'POST' })
  if (res.status === 200) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new PublishFormError('NOT_FOUND')
  if (res.status === 409) throw new PublishFormError('CONFLICT', 'Nothing to publish.')
  throw new PublishFormError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function forkFormDraft(formId: string, fromVersionId: string): Promise<FormVersion> {
  const res = await csrfFetch(`/forms/${formId}/fork`, { method: 'POST', body: JSON.stringify({ from_version_id: fromVersionId }) })
  if (res.status === 200 || res.status === 201) return formVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SaveFormError('NOT_FOUND')
  throw new SaveFormError('UNKNOWN', `Unexpected status ${res.status}`)
}
