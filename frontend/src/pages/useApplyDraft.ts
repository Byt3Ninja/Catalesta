import { useCallback, useEffect, useRef, useState } from 'react'

export interface ApplyDraft {
  answers: Record<string, unknown>
  idempotencyKey: string
}

function draftKey(cohortId: string): string {
  return `apply-draft:${cohortId}`
}

function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  // Fallback (jsdom without randomUUID) — non-cryptographic but unique enough.
  return `key-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function readDraft(cohortId: string): ApplyDraft {
  try {
    const raw = localStorage.getItem(draftKey(cohortId))
    if (raw) {
      const parsed = JSON.parse(raw) as Partial<ApplyDraft>
      if (parsed && typeof parsed === 'object') {
        return {
          answers: (parsed.answers as Record<string, unknown>) ?? {},
          idempotencyKey:
            typeof parsed.idempotencyKey === 'string'
              ? parsed.idempotencyKey
              : newIdempotencyKey(),
        }
      }
    }
  } catch {
    // Corrupt draft — start fresh.
  }
  return { answers: {}, idempotencyKey: newIdempotencyKey() }
}

/**
 * Per-cohort local draft: restores answers on mount, persists every change, and
 * keeps ONE Idempotency-Key for the draft's lifetime so a retried submit dedups
 * to the same receipt. `clear` wipes the draft on a successful receipt. Files
 * are intentionally not persisted (the File API is not serializable).
 */
export function useApplyDraft(cohortId: string) {
  // Lazy init so we restore from localStorage exactly once.
  const [draft, setDraft] = useState<ApplyDraft>(() => readDraft(cohortId))
  const cohortRef = useRef(cohortId)

  // If the cohort changes, reload its own draft.
  useEffect(() => {
    if (cohortRef.current !== cohortId) {
      cohortRef.current = cohortId
      setDraft(readDraft(cohortId))
    }
  }, [cohortId])

  useEffect(() => {
    try {
      localStorage.setItem(draftKey(cohortId), JSON.stringify(draft))
    } catch {
      // Storage unavailable (private mode / quota) — in-memory state still works.
    }
  }, [cohortId, draft])

  const setAnswer = useCallback((key: string, value: unknown) => {
    setDraft((prev) => ({ ...prev, answers: { ...prev.answers, [key]: value } }))
  }, [])

  const clear = useCallback(() => {
    try {
      localStorage.removeItem(draftKey(cohortId))
    } catch {
      // ignore
    }
  }, [cohortId])

  return { answers: draft.answers, idempotencyKey: draft.idempotencyKey, setAnswer, clear }
}
