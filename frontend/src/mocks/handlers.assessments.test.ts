import { expect, test } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from './handlers'

function hasRoute(method: string, frag: string): boolean {
  return handlers.some((h) => (h.info?.method as string) === method && String(h.info?.path ?? '').includes(frag))
}

test('scoring model handlers are registered', () => {
  expect(hasRoute('GET', '/programs/:programId/scoring-models')).toBe(true)
  expect(hasRoute('POST', '/programs/:programId/scoring-models')).toBe(true)
  expect(hasRoute('GET', '/scoring-models/:id')).toBe(true)
  expect(hasRoute('GET', '/scoring-models/:id/versions')).toBe(true)
  expect(hasRoute('GET', '/scoring-model-versions/:versionId')).toBe(true)
  expect(hasRoute('PATCH', '/scoring-models/:id/draft')).toBe(true)
  expect(hasRoute('POST', '/scoring-models/:id/publish')).toBe(true)
  expect(hasRoute('POST', '/scoring-models/:id/fork')).toBe(true)
  expect(hasRoute('GET', '/cohorts/:cohortId/stages/:stageId/assignments')).toBe(true)
  expect(hasRoute('GET', '/cohorts/:cohortId/stages/:stageId/scorecards/:applicationId/:reviewerId')).toBe(true)
})

test('bind-stage-scoring-model merge: binding stage B preserves stage A', async () => {
  const server = setupServer(...handlers)
  server.listen({ onUnhandledRequest: 'bypass' })
  try {
    // Bind stage_A → v1
    const resA = await fetch('http://localhost/api/v1/cohorts/coh_1/bind-stage-scoring-model', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ stage_id: 'stage_A', scoring_model_version_id: 'v1' }),
    })
    expect(resA.status).toBe(200)

    // Bind stage_B → v2; must not clobber stage_A
    const resB = await fetch('http://localhost/api/v1/cohorts/coh_1/bind-stage-scoring-model', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ stage_id: 'stage_B', scoring_model_version_id: 'v2' }),
    })
    expect(resB.status).toBe(200)
    const { data: cohort } = (await resB.json()) as { data: { stage_scoring_model_version_ids: Record<string, string> } }

    expect(cohort.stage_scoring_model_version_ids['stage_A']).toBe('v1')
    expect(cohort.stage_scoring_model_version_ids['stage_B']).toBe('v2')
  } finally {
    server.close()
  }
})

test('publish snapshots the draft into an immutable version and fork clones a version', async () => {
  const server = setupServer(...handlers)
  server.listen({ onUnhandledRequest: 'bypass' })
  try {
    // Create a scoring model so the store is populated.
    const createRes = await fetch('http://localhost/api/v1/programs/prog_1/scoring-models', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ name: 'Fork test model' }),
    })
    expect(createRes.status).toBe(201)
    const { data: model } = (await createRes.json()) as { data: { model_id: string } }

    // Patch the draft with a distinctive criterion.
    const criterion = { criterion_id: 'c_special', label: 'Special Criterion', max_points: 10, descriptors: null }
    await fetch(`http://localhost/api/v1/scoring-models/${model.model_id}/draft`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ criteria: [criterion] }),
    })

    // Publish → draft becomes an immutable published version.
    const pubRes = await fetch(`http://localhost/api/v1/scoring-models/${model.model_id}/publish`, {
      method: 'POST', headers: { 'X-XSRF-TOKEN': 'test' },
    })
    expect(pubRes.status).toBe(200)
    const { data: published } = (await pubRes.json()) as { data: { version_id: string; status: string } }
    expect(published.status).toBe('published')

    // Saving the now-published version is rejected (read-only): no draft remains.
    const reSave = await fetch(`http://localhost/api/v1/scoring-models/${model.model_id}/draft`, {
      method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' }, body: JSON.stringify({ criteria: [] }),
    })
    expect(reSave.status).toBe(404)

    // Fork from the published version clones its criteria into a new draft.
    const forkRes = await fetch(`http://localhost/api/v1/scoring-models/${model.model_id}/fork`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ from_version_id: published.version_id }),
    })
    expect(forkRes.status).toBe(200)
    const { data: forked } = (await forkRes.json()) as { data: { status: string; criteria: { criterion_id: string }[] } }
    expect(forked.status).toBe('draft')
    expect(forked.criteria.some((c) => c.criterion_id === 'c_special')).toBe(true)
  } finally {
    server.close()
  }
})
