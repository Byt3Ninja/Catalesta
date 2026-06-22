/**
 * Read the captured pre-login destination, clear it, and return it only if it is a
 * same-origin absolute path. Guards against open redirects (protocol-relative `//host`
 * or absolute URLs). Falls back to '/'. Shared by callback, login, and register.
 */
export function consumePostLoginRedirect(): string {
  const dest = sessionStorage.getItem('postLoginRedirect')
  sessionStorage.removeItem('postLoginRedirect')
  return dest && dest.startsWith('/') && !dest.startsWith('//') ? dest : '/'
}
