import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  confirmDeliverable,
  requestDeliverableUploadUrl,
  uploadFileToS3,
} from '../api/contractApi'
import { milestoneDeliverablesQueryKey } from './useContracts'
import type { Deliverable } from '../types'

export interface DeliverableUploadState {
  fileName: string
  progress: number
  status: 'uploading' | 'confirming' | 'done' | 'error'
  error?: string
  item?: Deliverable
}

interface UploadDeliverableInput {
  file: File
  description?: string
}

export function useDeliverableUpload(milestoneId: string) {
  const [uploads, setUploads] = useState<Record<string, DeliverableUploadState>>({})
  const queryClient = useQueryClient()

  const upload = async ({ file, description }: UploadDeliverableInput) => {
    const key = `${file.name}-${Date.now()}`
    setUploads((prev) => ({
      ...prev,
      [key]: { fileName: file.name, progress: 0, status: 'uploading' },
    }))

    try {
      const uploadUrl = await requestDeliverableUploadUrl(milestoneId, file.name)

      await uploadFileToS3(uploadUrl, file, (percent) => {
        setUploads((prev) => ({ ...prev, [key]: { ...prev[key], progress: percent } }))
      })

      setUploads((prev) => ({
        ...prev,
        [key]: { ...prev[key], status: 'confirming', progress: 100 },
      }))

      const item = await confirmDeliverable(milestoneId, {
        file_path: uploadUrl.path,
        mime_type: file.type || 'application/octet-stream',
        size_bytes: file.size,
        description,
      })

      setUploads((prev) => ({ ...prev, [key]: { ...prev[key], status: 'done', item } }))
      queryClient.invalidateQueries({ queryKey: milestoneDeliverablesQueryKey(milestoneId) })
      return item
    } catch (error) {
      setUploads((prev) => ({
        ...prev,
        [key]: {
          ...prev[key],
          status: 'error',
          error: error instanceof Error ? error.message : 'Upload failed.',
        },
      }))
      throw error
    }
  }

  return { uploads: Object.values(uploads), upload }
}
