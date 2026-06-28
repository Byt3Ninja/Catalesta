import { defineConfig, devices } from '@playwright/test'

/**
 * End-to-end harness. Tests run against an already-running stack (locally via
 * `docker compose up`, in CI via the `e2e` job). Override the target with E2E_BASE_URL.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:3000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      testIgnore: ['**/fe-ui-slice0.spec.ts', '**/fe-ui-slice1a.spec.ts', '**/fe-ui-slice1b.spec.ts', '**/fe-ui-slice2a.spec.ts', '**/fe-ui-slice2b.spec.ts'], // MSW-dev-server specs only
    },
    {
      name: 'msw-dev',
      testMatch: ['**/fe-ui-slice0.spec.ts', '**/fe-ui-slice1a.spec.ts', '**/fe-ui-slice1b.spec.ts', '**/fe-ui-slice2a.spec.ts', '**/fe-ui-slice2b.spec.ts'],
      use: { ...devices['Desktop Chrome'], baseURL: 'http://localhost:5173' },
    },
  ],
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
})
