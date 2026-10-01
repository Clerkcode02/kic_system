import { apiClient } from '@/lib/api'
import { confirmUpload, requestUploadUrl, uploadFileToS3 } from '@/lib/uploads'
import type { Attachment } from '@/lib/uploads'
import type {
  CreateProjectPayload,
  CursorPage,
  MyProjectListFilters,
  ProjectCategoryOption,
  ProjectDetail,
  ProjectListFilters,
  ProjectListItem,
  UpdateProjectPayload,
} from '../types'

export async function fetchProjectCategories(): Promise<ProjectCategoryOption[]> {
  const { data } = await apiClient.get<{ data: ProjectCategoryOption[] }>('/categories')
  return data.data
}

export async function fetchProjects(
  filters: ProjectListFilters,
  cursor?: string,
): Promise<CursorPage<ProjectListItem>> {
  const { data } = await apiClient.get<CursorPage<ProjectListItem>>('/projects', {
    params: { ...filters, cursor },
  })
  return data
}

export async function fetchProject(projectId: string): Promise<ProjectDetail> {
  const { data } = await apiClient.get<{ data: ProjectDetail }>(`/projects/${projectId}`)
  return data.data
}

/**
 * The client's own projects, in every status — distinct from
 * `fetchProjects`, which is the public browse list and only ever returns
 * open projects.
 */
export async function fetchMyProjects(
  filters: MyProjectListFilters = {},
  cursor?: string,
): Promise<CursorPage<ProjectListItem>> {
  const { data } = await apiClient.get<CursorPage<ProjectListItem>>('/me/projects', {
    params: { ...filters, cursor },
  })
  return data
}

export async function createProject(payload: CreateProjectPayload): Promise<ProjectDetail> {
  const { data } = await apiClient.post<{ data: ProjectDetail }>('/projects', payload)
  return data.data
}

export async function updateProject(
  projectId: string,
  payload: UpdateProjectPayload,
): Promise<ProjectDetail> {
  const { data } = await apiClient.patch<{ data: ProjectDetail }>(`/projects/${projectId}`, payload)
  return data.data
}

export async function cancelProject(projectId: string): Promise<void> {
  await apiClient.delete(`/projects/${projectId}`)
}

/**
 * Uploads a brief or reference file against an *existing* project.
 *
 * Attaching after creation rather than before is deliberate: a presigned
 * upload needs the attachable's id, so uploading first would mean either a
 * draft project (ruled out) or files orphaned in the bucket whenever the
 * client abandons the form.
 */
export async function uploadProjectAttachment(
  projectId: string,
  file: File,
  onProgress: (percent: number) => void,
): Promise<Attachment> {
  const uploadUrl = await requestUploadUrl('project', projectId, file.name)

  await uploadFileToS3(uploadUrl, file, onProgress)

  return confirmUpload('project', projectId, {
    path: uploadUrl.path,
    mimeType: file.type || 'application/octet-stream',
    sizeBytes: file.size,
  })
}
