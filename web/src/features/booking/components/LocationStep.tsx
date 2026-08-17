import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { cn } from '@/lib/cn'
import { AddressFields, LocationPicker, type AddressFieldValues, type LatLng } from '@/lib/maps'
import { ApiError } from '@/lib/api'
import { useMyAddresses } from '../hooks/useAddresses'
import type { WizardAddress } from '@/stores/bookingWizardStore'

interface LocationStepProps {
  /** Anonymous visitors have no saved addresses and always enter one inline. */
  isAuthenticated: boolean
  addressId: string | null
  address: WizardAddress
  onAddressIdChange: (addressId: string) => void
  onAddressChange: (address: WizardAddress) => void
  onNext: () => void
  onBack: () => void
}

function toFieldValues(address: WizardAddress): AddressFieldValues {
  return {
    street: address.line1,
    unit: address.line2,
    city: address.city,
    province: address.province,
    postal_code: address.postal_code,
  }
}

/**
 * One location step for both actor kinds (SRS §6.1) — not a guest fork.
 * The only difference is that the saved-address picker is absent when there
 * is no account to have saved any, which is a rendering condition rather
 * than a separate component.
 *
 * Geocoding happens once, on submit, inside LocationPicker — never per
 * keystroke — and is Canada-scoped (CLAUDE.md §5 "Location").
 */
export function LocationStep({
  isAuthenticated,
  addressId,
  address,
  onAddressIdChange,
  onAddressChange,
  onNext,
  onBack,
}: LocationStepProps) {
  const { data: addresses, isLoading } = useMyAddresses(isAuthenticated)
  const savedAddresses = isAuthenticated ? (addresses ?? []) : []

  const [isEnteringNew, setIsEnteringNew] = useState(!isAuthenticated)
  const [fields, setFields] = useState<AddressFieldValues>(toFieldValues(address))
  const [coords, setCoords] = useState<LatLng | null>(
    address.lat !== null && address.lng !== null ? { lat: address.lat, lng: address.lng } : null,
  )

  const mapAddress = [fields.street, fields.city, fields.province].filter(Boolean).join(', ')

  const commitInlineAddress = (): boolean => {
    if (!coords) {
      toast.error('Drag the pin or locate your address on the map first.')
      return false
    }
    if (!fields.street || !fields.city || !fields.province || !fields.postal_code) {
      toast.error('Fill in the street, city, province and postal code.')
      return false
    }

    onAddressChange({
      line1: fields.street,
      line2: fields.unit ?? '',
      city: fields.city,
      province: fields.province,
      postal_code: fields.postal_code,
      lat: coords.lat,
      lng: coords.lng,
    })

    return true
  }

  const handleContinue = () => {
    try {
      if (isEnteringNew) {
        if (!commitInlineAddress()) return
      } else if (!addressId) {
        toast.error('Choose an address, or enter a new one.')
        return
      }
      onNext()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not use this address.')
    }
  }

  if (isAuthenticated && isLoading) return null

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Service location</h2>

      {!isEnteringNew && (
        <div className="flex flex-col gap-2">
          {savedAddresses.map((saved) => (
            <label
              key={saved.id}
              className={cn(
                'flex cursor-pointer items-start gap-3 rounded-md border p-3',
                addressId === saved.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200',
              )}
            >
              <input
                type="radio"
                name="address"
                className="mt-1"
                checked={addressId === saved.id}
                onChange={() => onAddressIdChange(saved.id)}
              />
              <span className="text-sm">
                <span className="font-medium text-gray-900">{saved.label ?? 'Address'}</span>
                <br />
                {saved.street}
                {saved.unit ? `, ${saved.unit}` : ''}, {saved.city}, {saved.state_province}{' '}
                {saved.postal_code}
              </span>
            </label>
          ))}
          {savedAddresses.length === 0 && (
            <p className="text-sm text-gray-500">No saved addresses yet. Add one below.</p>
          )}
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setIsEnteringNew(true)}
            className="self-start"
          >
            + Use a different address
          </Button>
        </div>
      )}

      {isEnteringNew && (
        <Card className="flex flex-col gap-4">
          <AddressFields values={fields} onChange={setFields} />
          <LocationPicker
            address={mapAddress}
            onAddressChange={() => {}}
            addressReadOnly
            coords={coords}
            onCoordsChange={setCoords}
          />
          {isAuthenticated && savedAddresses.length > 0 && (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => setIsEnteringNew(false)}
              className="self-start"
            >
              Use a saved address instead
            </Button>
          )}
        </Card>
      )}

      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="button" onClick={handleContinue} className="flex-1">
          Continue
        </Button>
      </div>
    </div>
  )
}
