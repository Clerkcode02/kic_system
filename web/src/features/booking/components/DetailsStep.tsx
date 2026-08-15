import { Button } from '@/components'

interface DetailsStepProps {
  notes: string
  onChange: (notes: string) => void
  onNext: () => void
  onBack: () => void
}

export function DetailsStep({ notes, onChange, onNext, onBack }: DetailsStepProps) {
  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Anything the provider should know?</h2>
      <div className="flex flex-col gap-1">
        <label htmlFor="notes" className="text-sm font-medium text-gray-700">
          Notes (optional)
        </label>
        <textarea
          id="notes"
          rows={4}
          maxLength={1000}
          value={notes}
          onChange={(event) => onChange(event.target.value)}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Access instructions, specific concerns, etc."
        />
      </div>
      <p className="text-xs text-gray-500">
        Photo attachments aren&apos;t available yet — you can add them from the booking once
        it&apos;s created if the provider requests them.
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
