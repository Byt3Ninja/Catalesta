import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import './styles/tokens.css'
import { App } from './app/App'
import { DirectionProvider } from './app/DirectionProvider'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <DirectionProvider>
      <App />
    </DirectionProvider>
  </StrictMode>,
)
