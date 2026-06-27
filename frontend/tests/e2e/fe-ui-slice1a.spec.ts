import { test, expect } from '@playwright/test'

// Foundation proof: home renders the role-scoped Action Center; switching role
// changes the sections and the sidebar nav — all from MSW.
test('Action Center renders and reacts to role switch', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Acme Incubator', level: 1 })).toBeVisible({ timeout: 15000 })
  await expect(page.getByText('Review 4 delayed applications')).toBeVisible() // program_manager
  await expect(page.getByRole('link', { name: 'Programs' })).toBeVisible()

  await page.getByRole('button', { name: /Program Manager/ }).click()
  await page.getByRole('menuitem', { name: 'Founder' }).click()

  await expect(page.getByText('Complete the Team section')).toBeVisible() // founder
  await expect(page.getByRole('link', { name: 'My Startup' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Programs' })).toHaveCount(0)
})
