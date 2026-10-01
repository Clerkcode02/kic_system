import { apiClient } from '@/lib/api'

/**
 * Presigned direct-to-S3 uploads (CLAUDE.md §9.5): the file goes straight
 * from the browser to object storage, never multipart through the API. The
 * app server only hands out a URL and is told afterwards what landed.
 *
 * `attachable_type` must be on the backend's allow-list
 * (RequestUploadUrlRequest::ALLOWED_TYPES) and the resolved model's policy
 * must define `manageEvidence`, or the presign call returns 403.
 */
export type AttachableType = 'dispute' | 'project'

export interface PresignedUploadUrl {
  url: string
  path: string
  headers: Record<string, string>
}

export interface Attachment {
  id: string
  attachable_type: string
  attachable_id: string
  path: string
  mime_type: string
  size_bytes: number
  scanned: boolean
  created_at: string | null
}

export async function requestUploadUrl(
  attachableType: AttachableType,
  attachableId: string,
  filename: string,
): Promise<PresignedUploadUrl> {
  const { data } = await apiClient.post<{ data: PresignedUploadUrl }>('/uploads/presign', {
    attachable_type: attachableType,
    attachable_id: attachableId,
    filename,
  })
  return data.data
}

export async function confirmUpload(
  attachableType: AttachableType,
  attachableId: string,
  file: { path: string; mimeType: string; sizeBytes: number },
): Promise<Attachment> {
  const { data } = await apiClient.post<{ data: Attachment }>('/uploads/confirm', {
    attachable_type: attachableType,
    attachable_id: attachableId,
    file_path: file.path,
    mime_type: file.mimeType,
    size_bytes: file.sizeBytes,
  })
  return data.data
}

/**
 * PUTs the file to the presigned URL. Deliberately XHR rather than fetch —
 * fetch still has no upload-progress event, and a brief or deliverable can
 * be large enough that a progress bar matters.
 *
 * Does not go through `lib/api`'s axios instance on purpose: the target is
 * S3, not our API, and must not carry session cookies or auth headers.
 */
export function uploadFileToS3(
  uploadUrl: PresignedUploadUrl,
  file: File,
  onProgress: (percent: number) => void,
): Promise<void> {
  return new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('PUT', uploadUrl.url)
    Object.entries(uploadUrl.headers).forEach(([key, value]) => {
      xhr.setRequestHeader(key, value)
    })
    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100))
      }
    }
    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve()
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`))
      }
    }
    xhr.onerror = () => reject(new Error('Upload failed.'))
    xhr.send(file)
  })
}
