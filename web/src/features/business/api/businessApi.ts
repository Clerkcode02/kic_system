import { apiClient } from '@/lib/api'
import type {
  Business,
  BusinessDocument,
  BusinessDocumentType,
  ConfirmDocumentPayload,
  DocumentUploadUrl,
  UpdateBusinessProfilePayload,
} from '../types'

export async function fetchMyBusiness(): Promise<Business> {
  const { data } = await apiClient.get<{ data: Business }>('/provider/me')
  return data.data
}

export async function updateMyBusiness(payload: UpdateBusinessProfilePayload): Promise<Business> {
  const { data } = await apiClient.patch<{ data: Business }>('/provider/me', payload)
  return data.data
}

export async function requestDocumentUploadUrl(
  filename: string,
  documentType: BusinessDocumentType,
): Promise<DocumentUploadUrl> {
  const { data } = await apiClient.post<{ data: DocumentUploadUrl }>(
    '/provider/me/documents/upload-url',
    {
      filename,
      document_type: documentType,
    },
  )
  return data.data
}

export async function confirmBusinessDocument(
  payload: ConfirmDocumentPayload,
): Promise<BusinessDocument> {
  const { data } = await apiClient.post<{ data: BusinessDocument }>(
    '/provider/me/documents',
    payload,
  )
  return data.data
}

export async function submitBusinessForVerification(): Promise<void> {
  await apiClient.post('/provider/me/submit-for-verification')
}

/**
 * Uploads directly browser -> S3 against the presigned PUT URL (never
 * through the Laravel app server), reporting progress via XHR since axios
 * only exposes upload progress through the XHR adapter's event.
 */
export async function uploadFileToS3(
  uploadUrl: DocumentUploadUrl,
  file: File,
  onProgress: (percent: number) => void,
): Promise<void> {
  await new Promise<void>((resolve, reject) => {
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
