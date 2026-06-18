import { test, expect } from '@playwright/test'

/**
 * Smoke test: the SPA boots and renders its shell against the running stack.
 * Does not assert API health — that depends on backend services and is covered
 * by backend feature/contract tests.
 */
test('app shell loads', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { name: 'Catalesta', level: 1 })).toBeVisible()
})
