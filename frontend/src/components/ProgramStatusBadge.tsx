import type { Program } from '../schemas/programs'

const LABEL: Record<Program['status'], string> = {
  draft: 'Draft', published: 'Published', archived: 'Archived', closed: 'Closed',
}

/** Status pill — text label drives meaning (never colour alone). */
export function ProgramStatusBadge({ status }: { status: Program['status'] }) {
  return (
    <span
      data-status={status}
      className="inline-flex rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground"
    >
      {LABEL[status]}
    </span>
  )
}
