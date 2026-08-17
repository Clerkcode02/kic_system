export type BookingStatus =
  | 'pending'
  | 'waiting_for_quotation'
  | 'quotation_sent'
  | 'waiting_for_customer'
  | 'accepted'
  | 'scheduled'
  | 'in_progress'
  | 'completed'
  | 'declined'
  | 'quotation_expired'
  | 'cancelled'
  | 'refunded'

export type BookingPaymentStatus = 'unpaid' | 'partial' | 'paid' | 'refunded'

export type QuotationStatus = 'sent' | 'accepted' | 'rejected' | 'superseded' | 'expired'

/** Post-acceptance statuses where CancelBooking reports a cancellation fee applies (mirrors CancelBooking::POST_ACCEPTANCE_STATUSES). */
export const FEE_APPLIES_STATUSES: BookingStatus[] = ['accepted', 'scheduled', 'in_progress']

export interface Address {
  id: string
  label: string | null
  street: string
  unit: string | null
  city: string
  state_province: string
  postal_code: string
  country: string
  lat: number
  lng: number
  is_default: boolean
}

export interface BookingStatusHistoryEntry {
  id: string
  from_status: BookingStatus | null
  to_status: BookingStatus
  changed_by: string | null
  note: string | null
  created_at: string
}

export interface BookingAttachment {
  id: string
  uploaded_by: string
  file_path: string
  mime_type: string
  size_bytes: number
  caption: string | null
  created_at: string
}

export interface QuotationLineItem {
  id: string
  description: string
  quantity: string
  unit_price: string
  amount: string
  sort_order: number
}

export interface Quotation {
  id: string
  booking_id: string
  previous_quotation_id: string | null
  labor_cost: string
  materials_cost: string
  additional_fees: string
  platform_fee: string
  tax_amount: string
  discount_amount: string
  total_amount: string
  currency: string
  valid_until: string
  revision_number: number
  status: QuotationStatus
  line_items: QuotationLineItem[]
  created_at: string
  updated_at: string
}

export interface BookingListItem {
  id: string
  booking_number: string
  scheduled_date: string
  time_slot_start: string
  time_slot_end: string
  status: BookingStatus
  payment_status: BookingPaymentStatus
  service: { id: string; title: string; pricing_type: 'fixed' | 'quote' }
  provider: { id: string; legal_name: string }
  /** `id` is null for a booking placed as a guest (SRS §6.1). */
  customer: { id: string | null; name: string; is_guest: boolean }
  created_at: string
}

export interface BookingDetail extends Omit<BookingListItem, 'service' | 'provider'> {
  notes: string | null
  provider_completed_at: string | null
  service: {
    id: string
    title: string
    pricing_type: 'fixed' | 'quote'
    base_price: string
    currency: string
  }
  provider: { id: string; legal_name: string; rating_avg: number }
  address?: Address | null
  /**
   * Denormalized snapshot of where the work happens — always present, for
   * guest and registered bookings alike (SRS §6.1), which is what makes
   * "Book again" work for a claimed guest booking that never had an
   * `addresses` row.
   */
  service_address: {
    line1: string | null
    line2: string | null
    city: string | null
    province: string | null
    postal_code: string | null
  }
  status_history: BookingStatusHistoryEntry[]
  attachments: BookingAttachment[]
  quotations: Quotation[]
  updated_at: string
}

export interface CreateBookingPayload {
  service_id: string
  scheduled_date: string
  time_slot_start: string
  time_slot_end: string
  notes?: string
  /** A saved address — registered customers only (SRS §6.1). */
  address_id?: string
  /** Inline address; used when no saved address was chosen. */
  service_address?: {
    line1: string
    line2?: string | null
    city: string
    province: string
    postal_code: string
    lat: number
    lng: number
  }
}

export interface CursorPage<T> {
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
  }
}
