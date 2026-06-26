import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import './styles/tokens.css' // legacy — removed in a later slice
import { App } from './app/App'
import { DirectionProvider } from './app/DirectionProvider'
import type { Theme } from './app/direction-context'

function initialTheme(): Theme {
  try {
    const saved = localStorage.getItem('catalesta.theme')
    if (saved === 'dark' || saved === 'light') return saved
  } catch {
    /* ignore */
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

async function enableMocks(): Promise<void> {
  if (!import.meta.env.DEV) return
  if (import.meta.env.VITE_USE_MOCKS === 'false') return
  const { worker } = await import('./mocks/browser')
  await worker.start({ onUnhandledRequest: 'bypass' })
}

enableMocks().then(() => {
  createRoot(document.getElementById('root')!).render(
    <StrictMode>
      <DirectionProvider initialTheme={initialTheme()}>
        <App />
      </DirectionProvider>
    </StrictMode>,
  )
})
