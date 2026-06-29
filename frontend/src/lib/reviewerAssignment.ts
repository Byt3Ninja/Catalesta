/**
 * reviewerAssignment.ts — deterministic reviewer assignment engine
 *
 * No fetch, no React.  Pure function — given a list of application IDs, a
 * panel of reviewer IDs, and the target number of reviewers per application,
 * returns a balanced round-robin assignment.
 *
 * Algorithm:
 *   - perApp is clamped to panelReviewerIds.length so every assigned reviewer
 *     is distinct within one application.
 *   - A single rotating pointer advances across the panel array cyclically.
 *     Each application claims the next `perApp` slots, wrapping as needed.
 *   - Because the pointer moves by exactly `perApp` per application the load
 *     distribution is as balanced as possible (each reviewer count within ±1).
 */
export function assign(
  applicationIds: string[],
  panelReviewerIds: string[],
  perApp: number,
): { application_id: string; reviewer_ids: string[] }[] {
  const panelLen = panelReviewerIds.length

  if (panelLen === 0) {
    return applicationIds.map(application_id => ({ application_id, reviewer_ids: [] }))
  }

  const clamped = Math.min(perApp, panelLen)
  let pointer = 0

  return applicationIds.map(application_id => {
    const reviewer_ids: string[] = []
    for (let i = 0; i < clamped; i++) {
      reviewer_ids.push(panelReviewerIds[pointer % panelLen])
      pointer++
    }
    return { application_id, reviewer_ids }
  })
}
