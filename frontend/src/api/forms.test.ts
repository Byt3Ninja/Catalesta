import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { jsonResponse } from '../tests/test-utils'
import { createForm, saveFormDraft, publishForm, getFormVersion } from './forms'
import type { PublishFormError } from '../schemas/forms'

const FORM = { id: 'frm_1', name: 'Intake', description: null, latest_version: 1, published_version_ids: [], current_draft_version_id: 'fv_1' }
const DRAFT = { id: 'fv_1', form_id: 'frm_1', version: 1, status: 'draft', fields: [], created_at: 'x', published_at: null }

beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('createForm POSTs the name and returns the form', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: FORM }, 201))
  const form = await createForm('Intake')
  expect(form.id).toBe('frm_1')
  expect(spy.mock.calls[0][1]?.method).toBe('POST')
})

test('saveFormDraft PATCHes fields and returns the draft version', async () => {
  const fields = [{ id: 'f1', type: 'short_text', label: 'Name' }]
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, fields } }))
  const v = await saveFormDraft('frm_1', fields as never)
  expect(v.fields).toHaveLength(1)
  const body = JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}')
  expect(body.fields[0].id).toBe('f1')
})

test('publishForm returns a published version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
  const v = await publishForm('frm_1')
  expect(v.status).toBe('published')
})

test('publishForm 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(publishForm('frm_1')).rejects.toMatchObject({ code: 'CONFLICT' })
})

test('getFormVersion parses a version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  const v = await getFormVersion('fv_1')
  expect(v.version).toBe(1)
})
