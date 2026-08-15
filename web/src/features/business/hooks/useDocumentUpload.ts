import { useState } from 'react'
import {
  confirmBusinessDocument,
  requestDocumentUploadUrl,
  uploadFileToS3,
} from '../api/businessApi'
import type { BusinessDocument, BusinessDocumentType } from '../types'

export interface DocumentUploadState {
  fileName: string
  progress: number
  status: 'uploading' | 'confirming' | 'done' | 'error'
  error?: string
  document?: BusinessDocument
}

interface UploadDocumentInput {
  file: File
  documentType: BusinessDocumentType
  documentNumber: string
}

/**
 * Drives the presign -> browser-to-S3 PUT -> confirm sequence for a single
 * document, tracking progress so the UI can render a per-file progress bar.
 */
export function useDocumentUpload() {
  const [uploads, setUploads] = useState<Record<string, DocumentUploadState>>({})

  const upload = async ({ file, documentType, documentNumber }: UploadDocumentInput) => {
    const key = `${documentType}-${file.name}-${Date.now()}`
    setUploads((prev) => ({
      ...prev,
      [key]: { fileName: file.name, progress: 0, status: 'uploading' },
    }))

    try {
      const uploadUrl = await requestDocumentUploadUrl(file.name, documentType)

      await uploadFileToS3(uploadUrl, file, (percent) => {
        setUploads((prev) => ({ ...prev, [key]: { ...prev[key], progress: percent } }))
      })

      setUploads((prev) => ({
        ...prev,
        [key]: { ...prev[key], status: 'confirming', progress: 100 },
      }))

      const document = await confirmBusinessDocument({
        file_path: uploadUrl.path,
        document_type: documentType,
        document_number: documentNumber,
      })

      setUploads((prev) => ({ ...prev, [key]: { ...prev[key], status: 'done', document } }))
      return document
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
