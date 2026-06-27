import { test, expect } from '@playwright/test'

test('operator sets up a cohort end-to-end and opens it', async ({ page }) => {
  await page.goto('/programs/prog_1/cohorts/new')
  await expect(page.getByRole('heading', { name: 'Set up cohort' })).toBeVisible({ timeout: 15000 })

  await page.getByLabel(/cohort name/i).fill('E2E Cohort')
  await page.getByRole('button', { name: /create & continue/i }).click()

  await expect(page.getByRole('heading', { name: /attach form/i })).toBeVisible()
  await page.getByRole('button', { name: /skip for now/i }).click()
  await expect(page.getByRole('heading', { name: /attach stages/i })).toBeVisible()
  await page.getByRole('button', { name: /skip for now/i }).click()

  await expect(page.getByRole('heading', { name: /dates/i })).toBeVisible()
  await page.getByRole('button', { name: /continue/i }).click()

  await expect(page.getByRole('heading', { name: /review/i })).toBeVisible()
  await page.getByRole('button', { name: /open cohort/i }).click()

  await expect(page.getByText(/cohort is open/i)).toBeVisible()
})
