import type { BookingPaymentStatus, BookingStatus, QuotationStatus } from './types'

/**
 * The reduced read model a booking access token opens (SRS §6.1). It is
 * deliberately *not* `BookingDetail` with fields marked optional — the
 * backend resource is its own allow-list, and this type mirrors that so a
 * component can't reach for an id or a customer that will never be there.
 */
export interface GuestQuotationLineItem {
  description: string
  quantity: string
  unit_price: string
  amount: string
}

export interface GuestQuotation {
  id: string
  labor_cost: string
  materials_cost: string
  additional_fees: string
  platform_fee: string
  tax_amount: string
  discount_amount: string
  total_amount: string
  deposit_percentage: string | null
  currency: string
  valid_until: string
  revision_number: number
  status: QuotationStatus
  line_items: GuestQuotationLineItem[]
}

export interface GuestTimelineEntry {
  from_status: BookingStatus | null
  to_status: BookingStatus
  note: string | null
  occurred_at: string
}

export interface GuestServiceAddress {
  line1: string
  line2: string | null
  city: string
  province: string
  postal_code: string
}

export interface GuestBooking {
  booking_number: string
  status: BookingStatus
  payment_status: BookingPaymentStatus
  scheduled_date: string
  time_slot_start: string
  time_slot_end: string
  notes: string | null
  service: {
    title: string
    pricing_type: 'fixed' | 'quote'
    base_price: string
    currency: string
  }
  provider: {
    display_name: string
    rating_avg: number
  }
  service_address: GuestServiceAddress
  quotation: GuestQuotation | null
  timeline: GuestTimelineEntry[]
  created_at: string
}

export interface GuestContactPayload {
  guest_name: string
  guest_email: string
  guest_phone: string
}

export interface InlineServiceAddress {
  line1: string
  line2?: string | null
  city: string
  province: string
  postal_code: string
  lat: number
  lng: number
}

/**
 * The single create-booking payload. `address_id` (registered, saved
 * address) and `service_address` (inline) are mutually exclusive, as are
 * the guest contact fields and an authenticated session — the API returns
 * 422 for a mixture, mirroring the DB's exactly-one-actor constraint.
 */
export interface CreateBookingRequestPayload {
  service_id: string
  scheduled_date: string
  time_slot_start: string
  time_slot_end: string
  notes?: string
  address_id?: string
  service_address?: InlineServiceAddress
  guest_name?: string
  guest_email?: string
  guest_phone?: string
}

export interface GuestBookingCreated {
  booking: GuestBooking
  /** Returned exactly once, at creation. Never re-fetchable. */
  accessToken: string
}
