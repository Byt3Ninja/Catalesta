/**
 * Shared typed-error base for API clients. Each domain error carries a string
 * `code` union and a stable `name` (set by subclasses for instanceof-free checks).
 */
export class ApiError<C extends string> extends Error {
  readonly code: C
  constructor(code: C, message?: string) {
    super(message ?? code)
    this.code = code
  }
}
