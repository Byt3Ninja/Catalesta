import { test, expect } from '@playwright/test'

// Foundation proof: with MSW on, the app boots, the gate resolves via mocked
// session+orgs, and Programs renders the mocked list inside the new shell.
test('Programs renders from MSW with no backend', async ({ page }) => {
  await page.goto('/programs')
  await expect(page.getByRole('heading', { name: /Programs/, level: 1 })).toBeVisible({ timeout: 15000 })
  await expect(page.getByText('FinTech Accelerator 2026')).toBeVisible()
  await expect(page.getByRole('button', { name: /switch to (light|dark) theme/i })).toBeVisible()
})
