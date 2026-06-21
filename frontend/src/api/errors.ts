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

/** First string in a Laravel field-error array (`details.<field> = [msg, …]`). */
export function fieldMessage(value: unknown): string | undefined {
  return Array.isArray(value) && typeof value[0] === 'string' ? value[0] : undefined
}

/**
 * Parse the shared validation envelope `{ error: { details: { field: [msg] } } }`.
 * Returns the `details` map, or undefined if absent/unparseable.
 */
export async function readValidationDetails(
  response: Response,
): Promise<Record<string, unknown> | undefined> {
  try {
    const json = (await response.json()) as {
      error?: { details?: Record<string, unknown> }
    }
    return json?.error?.details
  } catch {
    return undefined
  }
}

/** First available field message across the whole details map. */
export function firstValidationMessage(
  details: Record<string, unknown> | undefined,
): string | undefined {
  return details
    ? Object.values(details)
        .map(fieldMessage)
        .find((m) => m !== undefined)
    : undefined
}
