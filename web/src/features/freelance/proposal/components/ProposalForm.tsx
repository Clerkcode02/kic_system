import { useState } from 'react'
import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import { Button, Card, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { useSubmitProposal } from '../hooks/useProposals'

const COVER_LETTER_MAX_LENGTH = 5000

interface ProposalFormProps {
  projectId: string
}

export function ProposalForm({ projectId }: ProposalFormProps) {
  const [proposedAmount, setProposedAmount] = useState('')
  const [deliveryDays, setDeliveryDays] = useState('')
  const [coverLetter, setCoverLetter] = useState('')
  const { mutateAsync, isPending } = useSubmitProposal(projectId)
  const navigate = useNavigate()

  const handleSubmit = async () => {
    try {
      await mutateAsync({
        proposed_amount: Number(proposedAmount),
        cover_letter: coverLetter,
        delivery_days: Number(deliveryDays),
      })
      toast.success('Proposal submitted.')
      navigate('/freelancer/proposals')
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not submit your proposal.')
    }
  }

  return (
    <Card>
      <div className="flex flex-col gap-4">
        <h2 className="text-sm font-semibold text-gray-900">Submit a proposal</h2>
        <Input
          label="Proposed amount (CAD)"
          type="number"
          min={0}
          step="0.01"
          required
          value={proposedAmount}
          onChange={(event) => setProposedAmount(event.target.value)}
        />
        <Input
          label="Delivery days"
          type="number"
          min={1}
          step="1"
          required
          value={deliveryDays}
          onChange={(event) => setDeliveryDays(event.target.value)}
        />
        <div className="flex flex-col gap-1">
          <label htmlFor="cover-letter" className="text-sm font-medium text-gray-700">
            Cover letter
          </label>
          <textarea
            id="cover-letter"
            rows={6}
            required
            maxLength={COVER_LETTER_MAX_LENGTH}
            value={coverLetter}
            onChange={(event) => setCoverLetter(event.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p className="text-xs text-gray-400">
            {coverLetter.length}/{COVER_LETTER_MAX_LENGTH}
          </p>
        </div>
        <Button type="button" isLoading={isPending} onClick={handleSubmit}>
          Submit proposal
        </Button>
      </div>
    </Card>
  )
}
