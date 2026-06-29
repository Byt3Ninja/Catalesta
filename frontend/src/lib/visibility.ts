import type { FormField, VisibilityCondition, VisibilityOperator } from '../schemas/forms'

/** The declarative operators the condition language supports. Field visibility
 *  and stage gating share this set — there is no second predicate dialect. */
export const KNOWN_OPERATORS: readonly VisibilityOperator[] = ['equals', 'not_equals', 'includes', 'is_empty']

export function matchCondition(cond: VisibilityCondition, state: Record<string, unknown>): boolean {
  const v = state[cond.field_id]
  switch (cond.operator) {
    case 'equals': return String(v ?? '') === String(cond.value ?? '')
    case 'not_equals': return String(v ?? '') !== String(cond.value ?? '')
    case 'includes': return Array.isArray(v) ? v.map(String).includes(String(cond.value ?? '')) : String(v ?? '').includes(String(cond.value ?? ''))
    case 'is_empty': return v == null || v === '' || (Array.isArray(v) && v.length === 0)
    default: return true
  }
}

/** Evaluate a declarative `{ match, conditions }` rule against state. A null or
 *  empty rule passes (no gate). Shared by field visibility and stage gating. */
export function evaluateRule(
  rule: { match: 'all' | 'any'; conditions: VisibilityCondition[] } | null | undefined,
  state: Record<string, unknown>,
): boolean {
  if (!rule || rule.conditions.length === 0) return true
  return rule.match === 'all'
    ? rule.conditions.every((c) => matchCondition(c, state))
    : rule.conditions.some((c) => matchCondition(c, state))
}

/** A field with no visibility rule is always shown. With a rule, evaluate its
 *  conditions against the current answers under match=all|any. */
export function isFieldVisible(field: FormField, answers: Record<string, unknown>): boolean {
  return evaluateRule(field.visibility ?? null, answers)
}
