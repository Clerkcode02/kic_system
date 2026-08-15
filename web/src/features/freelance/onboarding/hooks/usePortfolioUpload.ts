import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  confirmPortfolioItem,
  requestPortfolioUploadUrl,
  uploadFileToS3,
} from '../api/freelancerApi'
import { PORTFOLIO_QUERY_KEY } from './useFreelancerProfile'
import type { PortfolioItem } from '../types'

export interface PortfolioUploadState {
  fileName: string
  progress: number
  status: 'uploading' | 'confirming' | 'done' | 'error'
  error?: string
  item?: PortfolioItem
}

interface UploadPortfolioItemInput {
  file: File
  title: string
  description?: string
  projectUrl?: string
}

export function usePortfolioUpload() {
  const [uploads, setUploads] = useState<Record<string, PortfolioUploadState>>({})
  const queryClient = useQueryClient()

  const upload = async ({ file, title, description, projectUrl }: UploadPortfolioItemInput) => {
    const key = `${title}-${file.name}-${Date.now()}`
    setUploads((prev) => ({
      ...prev,
      [key]: { fileName: file.name, progress: 0, status: 'uploading' },
    }))

    try {
      const uploadUrl = await requestPortfolioUploadUrl(file.name)

      await uploadFileToS3(uploadUrl, file, (percent) => {
        setUploads((prev) => ({ ...prev, [key]: { ...prev[key], progress: percent } }))
      })

      setUploads((prev) => ({
        ...prev,
        [key]: { ...prev[key], status: 'confirming', progress: 100 },
      }))

      const item = await confirmPortfolioItem({
        title,
        description,
        file_path: uploadUrl.path,
        project_url: projectUrl,
      })

      setUploads((prev) => ({ ...prev, [key]: { ...prev[key], status: 'done', item } }))
      queryClient.invalidateQueries({ queryKey: PORTFOLIO_QUERY_KEY })
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
