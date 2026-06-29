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

  // CohortDetailPage now renders several binding pickers (form, stage pipeline, and one
  // per-stage scoring picker per stage) that ALL share the "Published version" label, so
  // getByLabel is ambiguous. Scope to the FORM picker by its stable id.
  const formSelect = page.locator('#form-binding-select')
  await expect(formSelect).toBeVisible({ timeout: 10000 })

  // Select a published version from the form picker's dropdown
  await formSelect.selectOption({ index: 1 })

  // Click Bind — scoped to the form picker (its Bind button is a sibling of the select)
  await formSelect.locator('..').getByRole('button', { name: /^bind$/i }).click()
  // FormBindingPicker shows its "Currently bound: …" label after success
  await expect(page.getByTestId('bound-label')).toBeVisible()
})
