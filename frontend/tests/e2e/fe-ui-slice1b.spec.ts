import { test, expect } from '@playwright/test'

// Notifications: open the center, assert unread indicator, mark all read, unread count → 0.
test('notifications: mark all read clears the unread count', async ({ page }) => {
  await page.goto('/notifications')
  await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible({ timeout: 15000 })
  await expect(page.getByText(/unread/i)).toBeVisible()
  await page.getByRole('button', { name: /mark all read/i }).click()
  await expect(page.getByText(/0 unread/i)).toBeVisible()
})

// Global search: typing a query surfaces a categorized result.
test('global search returns categorized results', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Acme Incubator', level: 1 })).toBeVisible({ timeout: 15000 })
  await page.getByRole('searchbox', { name: /search/i }).fill('fintech')
  await expect(page.getByText('FinTech Accelerator 2026')).toBeVisible()
})
