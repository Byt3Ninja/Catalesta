/**
 * Read the captured pre-login destination, clear it, and return it only if it is a
 * same-origin absolute path. Guards against open redirects: rejects protocol-relative
 * `//host`, backslash-host `/\host` (some user agents treat `\` as `/`), and absolute
 * URLs. Falls back to '/'. Shared by callback, login, and register.
 */
const SAFE_PATH = /^\/[^/\\]/
export function consumePostLoginRedirect(): string {
  const dest = sessionStorage.getItem('postLoginRedirect')
  sessionStorage.removeItem('postLoginRedirect')
  return dest && SAFE_PATH.test(dest) ? dest : '/'
}
