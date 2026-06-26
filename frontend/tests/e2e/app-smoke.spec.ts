import { test, expect } from '@playwright/test'

/**
 * Smoke test: the SPA boots and renders its shell against the running stack.
 * The react-web container serves Vite in dev mode with MSW on, so at `/` the
 * gate resolves via mocked session+orgs and renders the operator AppShell. We
 * assert the shell's banner + brand as a stable "SPA mounted" landmark. Does not
 * assert API health — that depends on backend services and is covered elsewhere.
 */
test('app shell loads', async ({ page }) => {
  await page.goto('/')

  // Generous timeout: the MSW service worker must register before the gate's
  // session/org queries resolve and the shell mounts.
  await expect(page.getByRole('banner')).toBeVisible({ timeout: 15000 })
  await expect(page.getByText('Catalesta')).toBeVisible()
})
