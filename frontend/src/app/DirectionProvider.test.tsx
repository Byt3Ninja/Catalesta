import { fireEvent, render, screen } from '@testing-library/react'
import { expect, test } from 'vitest'
import { DirectionProvider } from './DirectionProvider'
import { useDirection } from './direction-context'

function Probe() {
  const { dir, setDir, setTheme } = useDirection()
  return (
    <button
      onClick={() => {
        setDir('rtl')
        setTheme('dark')
      }}
    >
      dir={dir}
    </button>
  )
}

test('applies dir/lang/theme to <html> and flips them on demand', () => {
  render(
    <DirectionProvider>
      <Probe />
    </DirectionProvider>,
  )

  expect(document.documentElement.dir).toBe('ltr')
  expect(document.documentElement.lang).toBe('en')

  fireEvent.click(screen.getByRole('button'))

  expect(document.documentElement.dir).toBe('rtl')
  expect(document.documentElement.lang).toBe('ar')
  expect(document.documentElement.dataset.theme).toBe('dark')
})
