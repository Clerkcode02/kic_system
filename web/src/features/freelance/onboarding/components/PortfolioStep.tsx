import { useRef, useState } from 'react'
import { Button, Input } from '@/components'
import { usePortfolioUpload } from '../hooks/usePortfolioUpload'
import { useMyPortfolio } from '../hooks/useFreelancerProfile'

interface PortfolioStepProps {
  onNext: () => void
  onBack: () => void
}

export function PortfolioStep({ onNext, onBack }: PortfolioStepProps) {
  const [title, setTitle] = useState('')
  const [projectUrl, setProjectUrl] = useState('')
  const fileInputRef = useRef<HTMLInputElement>(null)
  const { uploads, upload } = usePortfolioUpload()
  const { data: existingItems } = useMyPortfolio()

  const handleUpload = async () => {
    const file = fileInputRef.current?.files?.[0]
    if (!file || !title) return

    await upload({ file, title, projectUrl: projectUrl || undefined }).catch(() => {})
    setTitle('')
    setProjectUrl('')
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const itemCount =
    (existingItems?.length ?? 0) + uploads.filter((item) => item.status === 'done').length

  return (
    <div className="flex flex-col gap-4">
      <Input label="Project title" value={title} onChange={(e) => setTitle(e.target.value)} />
      <Input
        label="Project URL (optional)"
        value={projectUrl}
        onChange={(e) => setProjectUrl(e.target.value)}
      />
      <div className="flex flex-col gap-1">
        <label htmlFor="portfolio-image" className="text-sm font-medium text-gray-700">
          Image
        </label>
        <input
          id="portfolio-image"
          ref={fileInputRef}
          type="file"
          accept="image/*"
          className="text-sm"
        />
      </div>
      <Button type="button" variant="secondary" onClick={handleUpload}>
        Add portfolio item
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

      <p className="text-xs text-gray-500">
        {itemCount} portfolio item(s) added. Optional — you can skip this step.
      </p>

      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="button" onClick={onNext} className="flex-1">
          Continue
        </Button>
      </div>
    </div>
  )
}
