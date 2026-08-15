import type { BusinessHours } from '@/features/auth/types'

export type BusinessVerificationStatus = 'pending' | 'verified' | 'rejected'

export type BusinessDocumentType =
  'business_license' | 'tax_certificate' | 'government_id' | 'insurance_certificate' | 'other'

export type DocumentVerificationStatus = 'pending' | 'verified' | 'rejected'

export interface BusinessAddress {
  street: string | null
  unit: string | null
  city: string | null
  province: string | null
  postal_code: string | null
}

export interface Business {
  id: string
  legal_name: string
  registration_number: string
  verification_status: BusinessVerificationStatus
  business_hours: BusinessHours
  rating_avg: number
  max_bookings_per_day: number
  address: BusinessAddress
  location: { lat: number; lng: number } | null
  created_at: string
  updated_at: string
}

export interface UpdateBusinessProfilePayload {
  legal_name?: string
  business_hours?: BusinessHours
  max_bookings_per_day?: number
  street?: string
  unit?: string | null
  city?: string
  province?: string
  postal_code?: string
  lat?: number
  lng?: number
}

export interface BusinessDocument {
  id: string
  document_type: BusinessDocumentType
  file_path: string
  issuing_authority: string | null
  issued_at: string | null
  expires_at: string | null
  verification_status: DocumentVerificationStatus
  rejection_reason: string | null
  created_at: string
}

export interface DocumentUploadUrl {
  path: string
  url: string
  headers: Record<string, string>
  expires_at: string
}

export interface ConfirmDocumentPayload {
  file_path: string
  document_type: BusinessDocumentType
  document_number: string
  issuing_authority?: string
  issued_at?: string
  expires_at?: string
}
