import { PROGRAM_TYPE_LABEL, type ProgramType } from '../schemas/programs'

/** Program type badge. Renders nothing for an untyped program. */
export function ProgramTypeBadge({ type }: { type: ProgramType | null }) {
  if (type === null) return null
  return (
    <span className="inline-flex rounded-md border border-border px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
      {PROGRAM_TYPE_LABEL[type]}
    </span>
  )
}
