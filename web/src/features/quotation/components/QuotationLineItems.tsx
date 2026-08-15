import type { Quotation } from '@/features/booking/types'

/**
 * Renders a quotation's cost breakdown exactly as the server returned it —
 * platform fee, tax, and total are always server-computed (CLAUDE.md §2/§5),
 * this component never recalculates them. Shared between the customer-facing
 * `QuotationPanel` and the provider-facing builder/history views so both
 * sides render identical numbers from the same `Quotation` shape.
 */
export function QuotationLineItems({ quotation }: { quotation: Quotation }) {
  return (
    <div className="flex flex-col gap-1 text-sm">
      {quotation.line_items.map((item) => (
        <div key={item.id} className="flex justify-between">
          <span className="text-gray-600">
            {item.description} × {item.quantity}
          </span>
          <span className="text-gray-900">${item.amount}</span>
        </div>
      ))}
      <div className="mt-1 flex justify-between border-t border-gray-200 pt-1 text-xs text-gray-500">
        <span>Labor</span>
        <span>${quotation.labor_cost}</span>
      </div>
      <div className="flex justify-between text-xs text-gray-500">
        <span>Materials</span>
        <span>${quotation.materials_cost}</span>
      </div>
      <div className="flex justify-between text-xs text-gray-500">
        <span>Additional fees</span>
        <span>${quotation.additional_fees}</span>
      </div>
      <div className="flex justify-between text-xs text-gray-500">
        <span>Platform fee</span>
        <span>${quotation.platform_fee}</span>
      </div>
      <div className="flex justify-between text-xs text-gray-500">
        <span>Tax</span>
        <span>${quotation.tax_amount}</span>
      </div>
      {Number(quotation.discount_amount) > 0 && (
        <div className="flex justify-between text-xs text-gray-500">
          <span>Discount</span>
          <span>-${quotation.discount_amount}</span>
        </div>
      )}
      <div className="flex justify-between border-t border-gray-200 pt-1 font-semibold text-gray-900">
        <span>Total</span>
        <span>
          ${quotation.total_amount} {quotation.currency}
        </span>
      </div>
    </div>
  )
}
