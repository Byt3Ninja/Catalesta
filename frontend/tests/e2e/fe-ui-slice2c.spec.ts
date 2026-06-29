import { test, expect } from '@playwright/test'

test('operator builds a stage pipeline, publishes it, and binds it to a cohort', async ({ page }) => {
  // Build: open the seeded draft pipeline's builder, add a stage, publish.
  await page.goto('/programs/prog_1/stages/pl_draft/edit')
  await expect(page.getByRole('heading', { name: /stage builder|new pipeline/i })).toBeVisible({ timeout: 15000 })

  await page.getByRole('button', { name: /add review/i }).click()
  await page.getByRole('button', { name: /^publish$/i }).click()
  await expect(page.getByText(/published/i)).toBeVisible()

  // Bind: from cohort detail, pick a published pipeline version and bind it.
  await page.goto('/cohorts/coh_1')
  await expect(page.getByRole('heading')).toBeVisible()

  // Both binding pickers share the label "Published version" — scope to the stage select by id.
  const stageSelect = page.locator('#stage-binding-select')
  await expect(stageSelect).toBeVisible({ timeout: 10000 })
  await stageSelect.selectOption({ index: 1 })
  await stageSelect.locator('..').getByRole('button', { name: /^bind$/i }).click()

  // The stage picker shows "Currently bound: …" after a successful bind.
  await expect(page.getByTestId('bound-stage-label')).toBeVisible()
})
