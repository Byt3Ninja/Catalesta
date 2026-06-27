import type { FormField, VisibilityCondition } from '../schemas/forms'

function matchOne(cond: VisibilityCondition, answers: Record<string, unknown>): boolean {
  const v = answers[cond.field_id]
  switch (cond.operator) {
    case 'equals': return String(v ?? '') === String(cond.value ?? '')
    case 'not_equals': return String(v ?? '') !== String(cond.value ?? '')
    case 'includes': return Array.isArray(v) ? v.map(String).includes(String(cond.value ?? '')) : String(v ?? '').includes(String(cond.value ?? ''))
    case 'is_empty': return v == null || v === '' || (Array.isArray(v) && v.length === 0)
    default: return true
  }
}

/** A field with no visibility rule is always shown. With a rule, evaluate its
 *  conditions against the current answers under match=all|any. */
export function isFieldVisible(field: FormField, answers: Record<string, unknown>): boolean {
  const rule = field.visibility
  if (!rule || rule.conditions.length === 0) return true
  return rule.match === 'all'
    ? rule.conditions.every((c) => matchOne(c, answers))
    : rule.conditions.some((c) => matchOne(c, answers))
}
