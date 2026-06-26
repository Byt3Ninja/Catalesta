import type { AnchorHTMLAttributes, ReactNode } from 'react'

/** Styled anchor with a visible focus ring (never removed). */
export function Link({ children, className, ...rest }: AnchorHTMLAttributes<HTMLAnchorElement> & { children: ReactNode }) {
  return (
    <a
      className={
        'rounded text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ' +
        (className ?? '')
      }
      {...rest}
    >
      {children}
    </a>
  )
}
