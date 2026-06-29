import { test, expect } from '@playwright/test'

/**
 * FE UI Slice 2d — Assessments end-to-end
 *
 * Flow:
 *   1. Author a scoring model: open the seeded sm_draft builder, add a criterion,
 *      publish → creates a published scoring-model version.
 *   2. Bind the already-published pl_pub pipeline (plv_pub_1, which carries the
 *      seeded s_screen stage) to the cohort so the scoring-binding section appears.
 *   3. Bind the new published scoring-model version to the s_screen stage.
 *   4. Generate reviewer assignments via the API (no dedicated UI yet).
 *   5. Reviewer clicks the first Review link, fills criterion scores, submits.
 *   6. Manager sets a cutoff, proposes decisions, then commits them.
 */

test('operator authors a scoring model, reviewer scores an application, manager commits a decision', async ({ page }) => {
  // ── 1. Build: open the seeded draft scoring model, add a criterion, publish ──
  await page.goto('/programs/prog_1/scoring/sm_draft/edit')
  await expect(page.getByRole('heading', { name: /scoring model builder/i })).toBeVisible({ timeout: 15000 })

  await page.getByRole('button', { name: /add criterion/i }).click()
  await page.getByRole('button', { name: /^publish$/i }).click()
  await expect(page.getByText(/published/i)).toBeVisible()

  // ── 2. Prerequisite: bind the seeded published pl_pub pipeline to coh_1 ────────
  //    plv_pub_1 carries stages s_screen / s_interview / s_decide (seeded IDs).
  //    The scoring-binding section only renders once stage_pipeline_version_id is set.
  await page.goto('/cohorts/coh_1')
  await expect(page.getByRole('heading')).toBeVisible({ timeout: 10000 })

  const stageSelect = page.locator('#stage-binding-select')
  await expect(stageSelect).toBeVisible({ timeout: 10000 })
  await stageSelect.selectOption({ index: 1 })           // first (and only) published version of pl_pub
  await stageSelect.locator('..').getByRole('button', { name: /^bind$/i }).click()
  await expect(page.getByTestId('bound-stage-label')).toBeVisible()

  // ── 3. Bind the published scoring-model version to s_screen ──────────────────
  //    Three stages render, each with id="scoring-binding-select" — use .first()
  //    which maps to Screening (s_screen, order 0).
  const scoringSelect = page.locator('#scoring-binding-select').first()
  await expect(scoringSelect).toBeVisible({ timeout: 10000 })
  await scoringSelect.selectOption({ index: 1 })
  await scoringSelect.locator('..').getByRole('button', { name: /^bind$/i }).click()
  await expect(page.getByTestId('stage-scoring-bound-s_screen')).not.toContainText('not configured')

  // ── 4. Generate assignments via the API (no dedicated UI component yet) ────────
  await page.evaluate(async () => {
    await fetch('/api/v1/cohorts/coh_1/stages/s_screen/assignments', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reviewer_ids: ['acc_demo'], per_app: 1 }),
    })
  })

  // ── 5. Reviewer: open the review queue, click the first Review link ───────────
  await page.goto('/cohorts/coh_1/stages/s_screen/review')
  await expect(page.getByRole('heading', { name: /review queue/i })).toBeVisible({ timeout: 15000 })

  // Each row's CTA says "Review" — click the first one
  const reviewLink = page.getByRole('link', { name: /^review$/i }).first()
  await expect(reviewLink).toBeVisible({ timeout: 10000 })
  await reviewLink.click()

  // Scorecard page must load criteria
  await expect(page.getByRole('heading', { name: /scorecard/i })).toBeVisible({ timeout: 15000 })

  const criterionInputs = page.locator('input[id^="criterion-"]')
  const count = await criterionInputs.count()
  for (let i = 0; i < count; i++) {
    await criterionInputs.nth(i).fill('8')
  }

  await page.getByRole('button', { name: /^submit$/i }).click()
  await expect(page.getByText(/submitted/i)).toBeVisible()

  // ── 6. Manager: submissions → set cutoff → propose → commit ──────────────────
  await page.goto('/cohorts/coh_1/submissions')
  await expect(page.getByRole('heading')).toBeVisible({ timeout: 15000 })

  // Stage selector (if present)
  const stageSelector = page.locator('#lb-stage-select')
  if (await stageSelector.isVisible()) {
    await stageSelector.selectOption('s_screen')
  }

  const cutoffInput = page.locator('#lb-cutoff')
  await expect(cutoffInput).toBeVisible({ timeout: 10000 })
  await cutoffInput.fill('5')

  await page.getByRole('button', { name: /^propose$/i }).click()

  const commitBtn = page.getByRole('button', { name: /^commit$/i })
  await expect(commitBtn).toBeVisible({ timeout: 10000 })
  await commitBtn.click()

  await expect(page.getByText(/committed/i)).toBeVisible()
})
