import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import toast from 'react-hot-toast'
import { Button, Card, Input } from '@/components'
import { ApiError } from '@/lib/api'
import { useSendQuotation, useReviseQuotation } from '../hooks/useQuotationActions'
import { quotationBuilderSchema, type QuotationBuilderFormValues } from '../schemas'
import type { QuotationBuilderPayload } from '../api/quotationApi'

interface QuotationBuilderFormProps {
  bookingId: string
  /** Present when revising an existing sent quotation; absent for the first send. */
  revisingQuotationId?: string
  onSuccess?: () => void
  onCancel?: () => void
}

const EMPTY_VALUES: QuotationBuilderFormValues = {
  labor_cost: 0,
  materials_cost: 0,
  additional_fees: 0,
  discount_amount: 0,
  line_items: [],
}

function toPayload(values: QuotationBuilderFormValues): QuotationBuilderPayload {
  return {
    labor_cost: values.labor_cost,
    materials_cost: values.materials_cost,
    additional_fees: values.additional_fees,
    discount_amount: values.discount_amount ?? undefined,
    line_items: values.line_items?.length ? values.line_items : undefined,
  }
}

/**
 * Sends a new quotation, or revises an existing one when `revisingQuotationId`
 * is passed. Only raw cost inputs are collected — the server (SendQuotation/
 * ReviseQuotation) always recomputes platform fee, tax, and total, so this
 * form never renders a client-side estimate of them.
 */
export function QuotationBuilderForm({
  bookingId,
  revisingQuotationId,
  onSuccess,
  onCancel,
}: QuotationBuilderFormProps) {
  const {
    register,
    control,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<QuotationBuilderFormValues>({
    resolver: zodResolver(quotationBuilderSchema),
    defaultValues: EMPTY_VALUES,
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'line_items' })
  const { mutateAsync: send } = useSendQuotation(bookingId)
  const { mutateAsync: revise } = useReviseQuotation(bookingId)

  const onSubmit = async (values: QuotationBuilderFormValues) => {
    try {
      const payload = toPayload(values)
      if (revisingQuotationId) {
        await revise({ quotationId: revisingQuotationId, payload })
        toast.success('Revised quotation sent.')
      } else {
        await send(payload)
        toast.success('Quotation sent.')
      }
      onSuccess?.()
    } catch (error) {
      if (error instanceof ApiError && error.kind === 'validation') {
        for (const [field, messages] of Object.entries(error.fieldErrors)) {
          if (field === 'labor_cost' || field === 'materials_cost' || field === 'additional_fees' || field === 'discount_amount') {
            setError(field, { message: messages[0] })
          }
        }
        toast.error(error.message)
        return
      }
      toast.error(error instanceof ApiError ? error.message : 'Could not send this quotation.')
    }
  }

  return (
    <Card className="flex flex-col gap-4">
      <h2 className="text-sm font-semibold text-gray-900">
        {revisingQuotationId ? 'Revise quotation' : 'Send quotation'}
      </h2>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Labor cost (CAD)"
            type="number"
            min={0}
            step="0.01"
            error={errors.labor_cost?.message}
            {...register('labor_cost')}
          />
          <Input
            label="Materials cost (CAD)"
            type="number"
            min={0}
            step="0.01"
            error={errors.materials_cost?.message}
            {...register('materials_cost')}
          />
          <Input
            label="Additional fees (CAD)"
            type="number"
            min={0}
            step="0.01"
            error={errors.additional_fees?.message}
            {...register('additional_fees')}
          />
          <Input
            label="Discount (CAD, optional)"
            type="number"
            min={0}
            step="0.01"
            error={errors.discount_amount?.message}
            {...register('discount_amount')}
          />
        </div>

        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-gray-700">Line items (optional)</span>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => append({ description: '', quantity: 1, unit_price: 0 })}
            >
              Add line item
            </Button>
          </div>
          {fields.map((field, index) => (
            <div key={field.id} className="grid grid-cols-[1fr_5rem_6rem_auto] items-end gap-2">
              <Input
                label={index === 0 ? 'Description' : undefined}
                error={errors.line_items?.[index]?.description?.message}
                {...register(`line_items.${index}.description` as const)}
              />
              <Input
                label={index === 0 ? 'Qty' : undefined}
                type="number"
                min={0}
                step="0.01"
                error={errors.line_items?.[index]?.quantity?.message}
                {...register(`line_items.${index}.quantity` as const)}
              />
              <Input
                label={index === 0 ? 'Unit price' : undefined}
                type="number"
                min={0}
                step="0.01"
                error={errors.line_items?.[index]?.unit_price?.message}
                {...register(`line_items.${index}.unit_price` as const)}
              />
              <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)}>
                Remove
              </Button>
            </div>
          ))}
        </div>

        <p className="text-xs text-gray-500">
          Platform fee, tax, and the total are calculated by the server after you submit.
        </p>

        <div className="flex gap-3">
          {onCancel && (
            <Button type="button" variant="secondary" onClick={onCancel} className="flex-1">
              Cancel
            </Button>
          )}
          <Button type="submit" isLoading={isSubmitting} className="flex-1">
            {revisingQuotationId ? 'Send revision' : 'Send quotation'}
          </Button>
        </div>
      </form>
    </Card>
  )
}
