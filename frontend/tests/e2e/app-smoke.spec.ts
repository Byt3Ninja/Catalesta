import { test, expect } from '@playwright/test'

/**
 * Smoke test: the SPA boots and renders its shell against the running stack.
 * At `/` with no session cookie, the app gate resolves to the LoginPage, so we
 * assert its level-1 heading as a stable "SPA mounted" landmark. Does not assert
 * API health — that depends on backend services and is covered by backend tests.
 */
test('app shell loads', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { name: 'Sign in', level: 1 })).toBeVisible()
})
