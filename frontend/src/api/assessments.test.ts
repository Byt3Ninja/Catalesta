import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { jsonResponse } from '../tests/test-utils'
import type { ScoringCriterion } from '../schemas/assessments'
import {
  listScoringModels, getScoringModel, getScoringModelVersion, listScoringModelVersions,
  createScoringModel, saveScoringModelDraft, publishScoringModel, forkScoringModelDraft,
  listAssignments, getScorecard,
} from './assessments'

const MODEL = { model_id: 'sm_1', program_id: 'prog_1', name: 'Technical Assessment', latest_version: 1, published_version_ids: [], current_draft_version_id: 'smv_1', created_at: 'x' }
const DRAFT = { version_id: 'smv_1', model_id: 'sm_1', version: 1, status: 'draft', criteria: [], created_at: 'x', published_at: null }
const CRITERION = { criterion_id: 'c1', label: 'Innovation', max_points: 10, descriptors: null }
const ASSIGNMENT = { assignment_id: 'asgn_1', cohort_id: 'coh_1', stage_id: 'stg_1', application_id: 'app_1', reviewer_id: 'rev_1', status: 'pending' }
const SCORECARD = { scorecard_id: 'sc_1', cohort_id: 'coh_1', stage_id: 'stg_1', application_id: 'app_1', reviewer_id: 'rev_1', model_version_id: 'smv_1', values: {}, disqualified: false, status: 'draft', submitted_at: null }

beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('listScoringModels returns the program models', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [MODEL] }))
  const list = await listScoringModels('prog_1')
  expect(list).toHaveLength(1)
  expect(list[0].model_id).toBe('sm_1')
})

test('listScoringModels 401 throws UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(listScoringModels('prog_1')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('getScoringModel returns the model', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: MODEL }))
  const m = await getScoringModel('sm_1')
  expect(m.name).toBe('Technical Assessment')
})

test('getScoringModel 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getScoringModel('nope')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('getScoringModelVersion parses a version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  const v = await getScoringModelVersion('smv_1')
  expect(v.version).toBe(1)
})

test('getScoringModelVersion 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getScoringModelVersion('nope')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('listScoringModelVersions returns versions', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))
  const vs = await listScoringModelVersions('sm_1')
  expect(vs).toHaveLength(1)
})

test('listScoringModelVersions 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(listScoringModelVersions('nope')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('createScoringModel POSTs the name and returns the model', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: MODEL }, 201))
  const m = await createScoringModel('prog_1', 'Technical Assessment')
  expect(m.model_id).toBe('sm_1')
  expect(spy.mock.calls[0][1]?.method).toBe('POST')
  expect(JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}').name).toBe('Technical Assessment')
})

test('createScoringModel 422 throws VALIDATION', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 422 }))
  await expect(createScoringModel('prog_1', '')).rejects.toMatchObject({ code: 'VALIDATION' })
})

test('saveScoringModelDraft PATCHes criteria and returns the draft', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, criteria: [CRITERION] } }))
  const v = await saveScoringModelDraft('sm_1', [CRITERION] as ScoringCriterion[])
  expect(v.criteria).toHaveLength(1)
  expect(JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}').criteria[0].criterion_id).toBe('c1')
})

test('saveScoringModelDraft 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(saveScoringModelDraft('sm_1', [])).rejects.toMatchObject({ code: 'CONFLICT' })
})

test('publishScoringModel returns a published version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
  const v = await publishScoringModel('sm_1')
  expect(v.status).toBe('published')
})

test('publishScoringModel 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(publishScoringModel('sm_1')).rejects.toMatchObject({ code: 'CONFLICT' })
})

test('forkScoringModelDraft sends from_version_id and returns a draft', async () => {
  const FORKED = { ...DRAFT, version_id: 'smv_2', version: 2 }
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: FORKED }))
  const v = await forkScoringModelDraft('sm_1', 'smv_pub_1')
  expect(v.version_id).toBe('smv_2')
  expect(v.status).toBe('draft')
  expect(JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}').from_version_id).toBe('smv_pub_1')
})

test('forkScoringModelDraft 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(forkScoringModelDraft('sm_1', 'nope')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('listAssignments returns assignments for cohort stage', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [ASSIGNMENT] }))
  const list = await listAssignments('coh_1', 'stg_1')
  expect(list).toHaveLength(1)
  expect(list[0].assignment_id).toBe('asgn_1')
})

test('listAssignments 401 throws UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(listAssignments('coh_1', 'stg_1')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('getScorecard returns the scorecard', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: SCORECARD }))
  const sc = await getScorecard('coh_1', 'stg_1', 'app_1', 'rev_1')
  expect(sc.scorecard_id).toBe('sc_1')
})

test('getScorecard 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getScorecard('coh_1', 'stg_1', 'nope', 'rev_1')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})
