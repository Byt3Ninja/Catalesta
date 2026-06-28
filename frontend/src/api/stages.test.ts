import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import { jsonResponse } from '../tests/test-utils'
import {
  listStagePipelines, getStagePipeline, getStagePipelineVersion, listStagePipelineVersions,
  listStageTemplates, createStagePipeline, saveStagePipelineDraft, publishStagePipeline, forkStagePipelineDraft,
} from './stages'

const PIPELINE = { pipeline_id: 'pl_1', program_id: 'prog_1', name: 'Acceleration', latest_version: 1, published_version_ids: [], current_draft_version_id: 'plv_1', created_at: 'x' }
const DRAFT = { version_id: 'plv_1', pipeline_id: 'pl_1', version: 1, status: 'draft', stages: [], created_at: 'x', published_at: null }

beforeEach(() => { Object.defineProperty(document, 'cookie', { value: 'XSRF-TOKEN=t', writable: true, configurable: true }) })
afterEach(() => vi.restoreAllMocks())

test('listStagePipelines returns the program pipelines', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [PIPELINE] }))
  const list = await listStagePipelines('prog_1')
  expect(list).toHaveLength(1)
  expect(list[0].pipeline_id).toBe('pl_1')
})

test('listStagePipelines 401 throws UNAUTHENTICATED', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 401 }))
  await expect(listStagePipelines('prog_1')).rejects.toMatchObject({ code: 'UNAUTHENTICATED' })
})

test('getStagePipeline returns the pipeline', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: PIPELINE }))
  const p = await getStagePipeline('pl_1')
  expect(p.name).toBe('Acceleration')
})

test('getStagePipeline 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getStagePipeline('nope')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('getStagePipelineVersion parses a version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: DRAFT }))
  const v = await getStagePipelineVersion('plv_1')
  expect(v.version).toBe(1)
})

test('getStagePipelineVersion 404 throws NOT_FOUND', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 404 }))
  await expect(getStagePipelineVersion('nope')).rejects.toMatchObject({ code: 'NOT_FOUND' })
})

test('listStagePipelineVersions returns versions', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [DRAFT] }))
  const vs = await listStagePipelineVersions('pl_1')
  expect(vs).toHaveLength(1)
})

test('listStageTemplates returns templates', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: [{ template_id: 't_review', name: 'Review', type: 'review' }] }))
  const ts = await listStageTemplates()
  expect(ts[0].type).toBe('review')
})

test('createStagePipeline POSTs the name and returns the pipeline', async () => {
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: PIPELINE }, 201))
  const p = await createStagePipeline('prog_1', 'Acceleration')
  expect(p.pipeline_id).toBe('pl_1')
  expect(spy.mock.calls[0][1]?.method).toBe('POST')
  expect(JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}').name).toBe('Acceleration')
})

test('createStagePipeline 422 throws VALIDATION', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 422 }))
  await expect(createStagePipeline('prog_1', '')).rejects.toMatchObject({ code: 'VALIDATION' })
})

test('saveStagePipelineDraft PATCHes stages and returns the draft', async () => {
  const stages = [{ stage_id: 's1', name: 'Screening', type: 'review', entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, order: 0 }]
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, stages } }))
  const v = await saveStagePipelineDraft('pl_1', stages as never)
  expect(v.stages).toHaveLength(1)
  expect(JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}').stages[0].stage_id).toBe('s1')
})

test('saveStagePipelineDraft 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(saveStagePipelineDraft('pl_1', [])).rejects.toMatchObject({ code: 'CONFLICT' })
})

test('publishStagePipeline returns a published version', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: { ...DRAFT, status: 'published', published_at: 'y' } }))
  const v = await publishStagePipeline('pl_1')
  expect(v.status).toBe('published')
})

test('publishStagePipeline 409 throws CONFLICT', async () => {
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(new Response(null, { status: 409 }))
  await expect(publishStagePipeline('pl_1')).rejects.toMatchObject({ code: 'CONFLICT' })
})

test('forkStagePipelineDraft sends from_version_id and returns a draft', async () => {
  const FORKED = { ...DRAFT, version_id: 'plv_2', version: 2 }
  const spy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(jsonResponse({ data: FORKED }))
  const v = await forkStagePipelineDraft('pl_1', 'plv_pub_1')
  expect(v.version_id).toBe('plv_2')
  expect(v.status).toBe('draft')
  expect(JSON.parse((spy.mock.calls[0][1]?.body as string) ?? '{}').from_version_id).toBe('plv_pub_1')
})
