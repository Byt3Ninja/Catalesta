import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import { configDefaults } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./vitest.setup.ts'],
    // Playwright owns tests/e2e; keep them out of the Vitest (jsdom) run.
    exclude: [...configDefaults.exclude, 'tests/e2e/**'],
  },
})
