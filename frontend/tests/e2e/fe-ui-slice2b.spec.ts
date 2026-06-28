import { test, expect } from '@playwright/test'

test('operator builds a form, publishes it, and binds it to a cohort', async ({ page }) => {
  await page.goto('/forms/frm_draft/edit')
  await expect(page.getByRole('heading', { name: /form builder|new form/i })).toBeVisible({ timeout: 15000 })

  await page.getByRole('button', { name: /add short text/i }).click()
  await page.getByRole('button', { name: /^publish$/i }).click()
  await expect(page.getByText(/published/i)).toBeVisible()

  // bind from cohort detail
  await page.goto('/cohorts/coh_1')
  await expect(page.getByRole('heading')).toBeVisible()

  // Wait for the form binding picker to load its options
  await expect(page.getByLabel(/published version/i)).toBeVisible({ timeout: 10000 })

  // Select a published version from the picker's dropdown
  await page.getByLabel(/published version/i).selectOption({ index: 1 })

  // Click Bind (now enabled because a version is selected)
  await page.getByRole('button', { name: /^bind$/i }).click()
  // FormBindingPicker shows "Currently bound: Application form v1" after success
  await expect(page.getByText(/currently bound/i)).toBeVisible()
})
