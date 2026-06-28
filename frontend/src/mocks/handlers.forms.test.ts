import { expect, test } from 'vitest'
import { setupServer } from 'msw/node'
import { handlers } from './handlers'

function hasRoute(method: string, frag: string): boolean {
  return handlers.some((h) => (h.info?.method as string) === method && String(h.info?.path ?? '').includes(frag))
}

test('forms handlers are registered', () => {
  expect(hasRoute('GET', '/forms')).toBe(true)
  expect(hasRoute('POST', '/forms')).toBe(true)
  expect(hasRoute('PATCH', '/forms/:id/draft')).toBe(true)
  expect(hasRoute('POST', '/forms/:id/publish')).toBe(true)
  expect(hasRoute('POST', '/forms/:id/fork')).toBe(true)
  expect(hasRoute('GET', '/forms/:id/versions')).toBe(true)
  expect(hasRoute('GET', '/form-versions/:versionId')).toBe(true)
})

test('fork handler clones fields from the specified from_version_id', async () => {
  // Spin up an MSW node server backed by the real handlers store.
  const server = setupServer(...handlers)
  server.listen({ onUnhandledRequest: 'bypass' })
  try {
    // Create a form via the POST /forms handler so the store is populated.
    const createRes = await fetch('http://localhost/api/v1/forms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ name: 'Fork test form' }),
    })
    expect(createRes.status).toBe(201)
    const { data: form } = (await createRes.json()) as { data: { id: string; current_draft_version_id: string } }

    // Patch the draft with distinctive fields
    const uniqueField = { id: 'f_special', type: 'short_text', label: 'Special field', required: false }
    await fetch(`http://localhost/api/v1/forms/${form.id}/draft`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ fields: [uniqueField] }),
    })

    // Publish to make it a published version
    const pubRes = await fetch(`http://localhost/api/v1/forms/${form.id}/publish`, {
      method: 'POST',
      headers: { 'X-XSRF-TOKEN': 'test' },
    })
    expect(pubRes.status).toBe(200)
    const { data: published } = (await pubRes.json()) as { data: { id: string } }

    // Fork from the specific published version id
    const forkRes = await fetch(`http://localhost/api/v1/forms/${form.id}/fork`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': 'test' },
      body: JSON.stringify({ from_version_id: published.id }),
    })
    expect(forkRes.status).toBe(200)
    const { data: forked } = (await forkRes.json()) as { data: { fields: { id: string }[] } }

    // The forked draft must contain the fields from the specified version
    expect(forked.fields.some((f) => f.id === 'f_special')).toBe(true)
  } finally {
    server.close()
  }
})
