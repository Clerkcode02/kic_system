import { create } from 'zustand'
import { persist, createJSONStorage } from 'zustand/middleware'

export interface WizardSlot {
  start: string
  end: string
}

export interface WizardAddress {
  line1: string
  line2: string
  city: string
  province: string
  postal_code: string
  lat: number | null
  lng: number | null
}

export interface WizardContact {
  name: string
  email: string
  phone: string
}

export const EMPTY_ADDRESS: WizardAddress = {
  line1: '',
  line2: '',
  city: '',
  province: '',
  postal_code: '',
  lat: null,
  lng: null,
}

export const EMPTY_CONTACT: WizardContact = { name: '', email: '', phone: '' }

interface BookingWizardState {
  serviceId: string | null
  stepIndex: number
  date: string
  slot: WizardSlot | null
  /** Set only for registered users choosing a saved address. */
  addressId: string | null
  /** The inline address; the only option for a guest. */
  address: WizardAddress
  contact: WizardContact
  notes: string
  /** One Idempotency-Key per submission, reused across retries. */
  idempotencyKey: string

  startFor: (serviceId: string) => void
  setStep: (stepIndex: number) => void
  setSchedule: (date: string, slot: WizardSlot | null) => void
  setAddressId: (addressId: string | null) => void
  setAddress: (address: WizardAddress) => void
  setContact: (contact: WizardContact) => void
  setNotes: (notes: string) => void
  /** Prefills from a previous booking ("Book again"), leaving the date blank. */
  prefill: (input: { serviceId: string; address: WizardAddress; addressId: string | null }) => void
  reset: () => void
}

function freshKey(): string {
  return crypto.randomUUID()
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

const INITIAL = {
  serviceId: null,
  stepIndex: 0,
  date: todayIso(),
  slot: null,
  addressId: null,
  address: EMPTY_ADDRESS,
  contact: EMPTY_CONTACT,
  notes: '',
} as const

/**
 * Wizard progress — **client state only** (CLAUDE.md §6: server state is
 * TanStack Query's, never mirrored here). Nothing in this store comes from
 * the API; it is purely what the user has typed so far.
 *
 * Persisted to `sessionStorage` so a detour to sign in or register
 * mid-wizard doesn't lose the form (SRS §6.1). Session-scoped rather than
 * `localStorage` because a half-finished booking, including a contact
 * email and a home address, should not outlive the tab.
 */
export const useBookingWizardStore = create<BookingWizardState>()(
  persist(
    (set, get) => ({
      ...INITIAL,
      idempotencyKey: freshKey(),

      startFor: (serviceId) => {
        // Switching services mid-flow must not carry the previous
        // service's schedule — or its idempotency key, which would make the
        // new submission replay the old booking's response.
        if (get().serviceId !== serviceId) {
          set({ ...INITIAL, serviceId, idempotencyKey: freshKey() })
        }
      },

      setStep: (stepIndex) => set({ stepIndex }),
      setSchedule: (date, slot) => set({ date, slot }),
      setAddressId: (addressId) => set({ addressId }),
      setAddress: (address) => set({ address, addressId: null }),
      setContact: (contact) => set({ contact }),
      setNotes: (notes) => set({ notes }),

      prefill: ({ serviceId, address, addressId }) =>
        set({
          ...INITIAL,
          serviceId,
          address,
          addressId,
          // The date is deliberately left blank rather than copied from the
          // previous booking: re-running a job means a *new* appointment,
          // and a stale prefilled date is far more likely to be submitted
          // by accident than noticed and corrected.
          date: '',
          slot: null,
          idempotencyKey: freshKey(),
        }),

      reset: () => set({ ...INITIAL, idempotencyKey: freshKey() }),
    }),
    {
      name: 'booking-wizard',
      storage: createJSONStorage(() => sessionStorage),
    },
  ),
)
