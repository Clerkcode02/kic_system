import { useRef, useState } from 'react'
import { Button, Input, Select } from '@/components'
import { useDocumentUpload } from '../hooks/useDocumentUpload'
import type { BusinessDocumentType } from '../types'

interface DocumentUploadStepProps {
  onNext: () => void
  onBack: () => void
}

const DOCUMENT_TYPE_OPTIONS: { value: BusinessDocumentType; label: string }[] = [
  { value: 'business_license', label: 'Business license' },
  { value: 'tax_certificate', label: 'Tax certificate' },
  { value: 'government_id', label: 'Government ID' },
  { value: 'insurance_certificate', label: 'Insurance certificate' },
  { value: 'other', label: 'Other' },
]

export function DocumentUploadStep({ onNext, onBack }: DocumentUploadStepProps) {
  const [documentType, setDocumentType] = useState<BusinessDocumentType>('business_license')
  const [documentNumber, setDocumentNumber] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)
  const { uploads, upload } = useDocumentUpload()

  const handleUpload = async () => {
    const file = fileInputRef.current?.files?.[0]
    if (!file || !documentNumber) return

    await upload({ file, documentType, documentNumber }).catch(() => {})
    setDocumentNumber('')
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const hasAtLeastOneDone = uploads.some((upload) => upload.status === 'done')

  return (
    <div className="flex flex-col gap-4">
      <Select
        label="Document type"
        options={DOCUMENT_TYPE_OPTIONS}
        value={documentType}
        onChange={(event) => setDocumentType(event.target.value as BusinessDocumentType)}
      />
      <Input
        label="Document number"
        value={documentNumber}
        onChange={(event) => setDocumentNumber(event.target.value)}
      />
      <div className="flex flex-col gap-1">
        <label htmlFor="document-file" className="text-sm font-medium text-gray-700">
          File
        </label>
        <input
          id="document-file"
          ref={fileInputRef}
          type="file"
          accept="image/*,.pdf"
          className="text-sm"
        />
      </div>
      <Button type="button" variant="secondary" onClick={handleUpload}>
        Upload document
      </Button>

      {uploads.length > 0 && (
        <ul className="flex flex-col gap-2">
          {uploads.map((item, index) => (
            <li key={`${item.fileName}-${index}`} className="text-sm">
              <div className="flex items-center justify-between">
                <span className="truncate text-gray-700">{item.fileName}</span>
                <span className="text-gray-500">
                  {item.status === 'error'
                    ? 'Failed'
                    : item.status === 'done'
                      ? 'Uploaded'
                      : `${item.progress}%`}
                </span>
              </div>
              <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                  className={`h-full rounded-full ${item.status === 'error' ? 'bg-red-500' : 'bg-blue-600'}`}
                  style={{ width: `${item.status === 'done' ? 100 : item.progress}%` }}
                />
              </div>
              {item.error && <p className="mt-1 text-xs text-red-600">{item.error}</p>}
            </li>
          ))}
        </ul>
      )}

      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="button" disabled={!hasAtLeastOneDone} onClick={onNext} className="flex-1">
          Continue
        </Button>
      </div>
    </div>
  )
}
