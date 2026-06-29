import { useQuery } from '@tanstack/react-query'
import { AppShell } from '../components/AppShell'
import { Link } from '../components/Link'
import { Spinner } from '../components/Loading'
import { StateBlock } from '../components/StateBlock'
import { listAssignments } from '../api/assessments'
import type { ReviewerAssignment } from '../schemas/assessments'

function StatusBadge({ status }: { status: ReviewerAssignment['status'] }) {
  const label = status === 'submitted' ? 'Submitted' : 'Pending'
  const cls =
    status === 'submitted'
      ? 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800'
      : 'rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-secondary-foreground'
  return <span data-status={status} className={cls}>{label}</span>
}

function AssignmentRow({
  assignment,
  index,
  cohortId,
  stageId,
}: {
  assignment: ReviewerAssignment
  index: number
  cohortId: string
  stageId: string
}) {
  const maskedLabel = `Application #${index + 1}`
  return (
    <div className="flex items-center justify-between gap-4 rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-3">
        <span className="font-medium text-sm">{maskedLabel}</span>
        <StatusBadge status={assignment.status} />
      </div>
      <Link
        href={`/cohorts/${cohortId}/stages/${stageId}/review/${assignment.application_id}`}
        className="text-sm text-primary underline-offset-2 hover:underline"
      >
        Review
      </Link>
    </div>
  )
}

export function ReviewQueuePage({
  cohortId,
  stageId,
  reviewerId,
}: {
  cohortId: string
  stageId: string
  reviewerId: string
}) {
  const assignmentsQuery = useQuery({
    queryKey: ['assignments', cohortId, stageId],
    queryFn: () => listAssignments(cohortId, stageId),
    retry: false,
  })

  const myAssignments = (assignmentsQuery.data ?? []).filter(
    (a) => a.reviewer_id === reviewerId,
  )

  return (
    <AppShell
      rail={
        <nav aria-label="Review navigation" className="grid gap-1 text-sm">
          <Link href="/cohorts">Cohorts</Link>
        </nav>
      }
      pageHeader={
        <h1 id="review-queue-heading" className="text-2xl font-semibold">
          Review Queue
        </h1>
      }
    >
      <section aria-labelledby="review-queue-heading" className="grid max-w-2xl gap-4">
        {assignmentsQuery.isLoading ? (
          <Spinner label="Loading review queue…" />
        ) : assignmentsQuery.isError ? (
          <StateBlock variant="error" message="Could not load your review assignments." />
        ) : myAssignments.length === 0 ? (
          <StateBlock variant="empty" message="No applications assigned to you yet." />
        ) : (
          myAssignments.map((assignment, index) => (
            <AssignmentRow
              key={assignment.assignment_id}
              assignment={assignment}
              index={index}
              cohortId={cohortId}
              stageId={stageId}
            />
          ))
        )}
      </section>
    </AppShell>
  )
}
