import { apiFetch } from './tenant'
import { csrfFetch } from './csrf'
import {
  GetPipelineError, SavePipelineError, PublishPipelineError,
  stagePipelineListResponseSchema, stagePipelineResponseSchema,
  stagePipelineVersionResponseSchema, stagePipelineVersionListResponseSchema,
  stageTemplateListResponseSchema,
  type StagePipeline, type StagePipelineVersion, type Stage, type StageTemplate,
} from '../schemas/stages'

export { GetPipelineError, SavePipelineError, PublishPipelineError }

export async function listStagePipelines(programId: string): Promise<StagePipeline[]> {
  const res = await apiFetch(`/programs/${programId}/stage-pipelines`)
  if (res.status !== 200) throw new GetPipelineError(res.status === 401 ? 'UNAUTHENTICATED' : 'UNKNOWN')
  return stagePipelineListResponseSchema.parse(await res.json()).data
}

export async function getStagePipeline(id: string): Promise<StagePipeline> {
  const res = await apiFetch(`/stage-pipelines/${id}`)
  if (res.status === 200) return stagePipelineResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetPipelineError('NOT_FOUND')
  if (res.status === 401) throw new GetPipelineError('UNAUTHENTICATED')
  throw new GetPipelineError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function getStagePipelineVersion(versionId: string): Promise<StagePipelineVersion> {
  const res = await apiFetch(`/stage-pipeline-versions/${versionId}`)
  if (res.status === 200) return stagePipelineVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new GetPipelineError('NOT_FOUND')
  throw new GetPipelineError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function listStagePipelineVersions(pipelineId: string): Promise<StagePipelineVersion[]> {
  const res = await apiFetch(`/stage-pipelines/${pipelineId}/versions`)
  if (res.status !== 200) throw new GetPipelineError(res.status === 404 ? 'NOT_FOUND' : 'UNKNOWN')
  return stagePipelineVersionListResponseSchema.parse(await res.json()).data
}

export async function listStageTemplates(): Promise<StageTemplate[]> {
  const res = await apiFetch('/stage-templates')
  if (res.status !== 200) throw new GetPipelineError('UNKNOWN')
  return stageTemplateListResponseSchema.parse(await res.json()).data
}

export async function createStagePipeline(programId: string, name: string): Promise<StagePipeline> {
  const res = await csrfFetch(`/programs/${programId}/stage-pipelines`, { method: 'POST', body: JSON.stringify({ name }) })
  if (res.status === 201) return stagePipelineResponseSchema.parse(await res.json()).data
  if (res.status === 422) throw new SavePipelineError('VALIDATION', 'The pipeline name is required.')
  throw new SavePipelineError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function saveStagePipelineDraft(pipelineId: string, stages: Stage[]): Promise<StagePipelineVersion> {
  const res = await csrfFetch(`/stage-pipelines/${pipelineId}/draft`, { method: 'PATCH', body: JSON.stringify({ stages }) })
  if (res.status === 200) return stagePipelineVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SavePipelineError('NOT_FOUND')
  if (res.status === 409) throw new SavePipelineError('CONFLICT', 'This version is published and read-only.')
  throw new SavePipelineError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function publishStagePipeline(pipelineId: string): Promise<StagePipelineVersion> {
  const res = await csrfFetch(`/stage-pipelines/${pipelineId}/publish`, { method: 'POST' })
  if (res.status === 200) return stagePipelineVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new PublishPipelineError('NOT_FOUND')
  if (res.status === 409) throw new PublishPipelineError('CONFLICT', 'Nothing to publish.')
  throw new PublishPipelineError('UNKNOWN', `Unexpected status ${res.status}`)
}

export async function forkStagePipelineDraft(pipelineId: string, fromVersionId: string): Promise<StagePipelineVersion> {
  const res = await csrfFetch(`/stage-pipelines/${pipelineId}/fork`, { method: 'POST', body: JSON.stringify({ from_version_id: fromVersionId }) })
  if (res.status === 200 || res.status === 201) return stagePipelineVersionResponseSchema.parse(await res.json()).data
  if (res.status === 404) throw new SavePipelineError('NOT_FOUND')
  throw new SavePipelineError('UNKNOWN', `Unexpected status ${res.status}`)
}
