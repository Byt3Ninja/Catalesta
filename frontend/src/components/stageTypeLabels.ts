/** Human labels for stage types — shared by the canvas, palette, and inspector.
 *  Kept in a constants-only module so component files stay fast-refresh clean. */
export const STAGE_TYPE_LABEL: Record<string, string> = {
  review: 'Review', interview: 'Interview', task: 'Task', decision: 'Decision', automated: 'Automated',
}
