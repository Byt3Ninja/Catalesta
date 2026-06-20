import type { AnchorHTMLAttributes, ReactNode } from 'react'

/** Styled anchor with the visible focus ring (never removed). */
export function Link({ children, ...rest }: AnchorHTMLAttributes<HTMLAnchorElement> & { children: ReactNode }) {
  return (
    <a className="ds-link ds-focusable" {...rest}>
      {children}
    </a>
  )
}
