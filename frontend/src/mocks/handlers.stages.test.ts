import { expect, test } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from './handlers'

function hasRoute(method: string, frag: string): boolean {
  return handlers.some((h) => (h.info?.method as string) === method && String(h.info?.path ?? '').includes(frag))
}

test('stage pipeline handlers are registered', () => {
  expect(hasRoute('GET', '/stage-templates')).toBe(true)
  expect(hasRoute('GET', '/programs/:programId/stage-pipelines')).toBe(true)
  expect(hasRoute('POST', '/programs/:programId/stage-pipelines')).toBe(true)
  expect(hasRoute('GET', '/stage-pipelines/:id')).toBe(true)
  expect(hasRoute('GET', '/stage-pipelines/:id/versions')).toBe(true)
  expect(hasRoute('GET', '/stage-pipeline-versions/:versionId')).toBe(true)
  expect(hasRoute('PATCH', '/stage-pipelines/:id/draft')).toBe(true)
  expect(hasRoute('POST', '/stage-pipelines/:id/publish')).toBe(true)
  expect(hasRoute('POST', '/stage-pipelines/:id/fork')).toBe(true)
})

test('publish snapshots the draft into an immutable version and fork clones a version', async () => {
  const server = setupServer(...handlers)
  server.listen({ onUnhandledRequest: 'bypass' })
  try {
    // Create a pipeline so the store is populated.
    const createRes = await fetch('http://localhost/api/v1/programs/prog_1/stage-pipelines', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ name: 'Fork test pipeline' }),
    })
    expect(createRes.status).toBe(201)
    const { data: pipeline } = (await createRes.json()) as { data: { pipeline_id: string } }

    // Patch the draft with a distinctive stage.
    const stage = { stage_id: 's_special', name: 'Special', type: 'review', entry_rule: null, exit_rule: null, next_stage_ids: [], depends_on_stage_ids: [], parallel_group: null, order: 0 }
    await fetch(`http://localhost/api/v1/stage-pipelines/${pipeline.pipeline_id}/draft`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ stages: [stage] }),
    })

    // Publish → draft becomes an immutable published version.
    const pubRes = await fetch(`http://localhost/api/v1/stage-pipelines/${pipeline.pipeline_id}/publish`, {
      method: 'POST', headers: { 'X-XSRF-TOKEN': 'test' },
    })
    expect(pubRes.status).toBe(200)
    const { data: published } = (await pubRes.json()) as { data: { version_id: string; status: string } }
    expect(published.status).toBe('published')

    // Saving the now-published version is rejected (read-only): no draft remains.
    const reSave = await fetch(`http://localhost/api/v1/stage-pipelines/${pipeline.pipeline_id}/draft`, {
      method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' }, body: JSON.stringify({ stages: [] }),
    })
    expect(reSave.status).toBe(404)

    // Fork from the published version clones its stages into a new draft.
    const forkRes = await fetch(`http://localhost/api/v1/stage-pipelines/${pipeline.pipeline_id}/fork`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ from_version_id: published.version_id }),
    })
    expect(forkRes.status).toBe(200)
    const { data: forked } = (await forkRes.json()) as { data: { status: string; stages: { stage_id: string }[] } }
    expect(forked.status).toBe('draft')
    expect(forked.stages.some((s) => s.stage_id === 's_special')).toBe(true)
  } finally {
    server.close()
  }
})
