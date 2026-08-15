import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button, Card } from '@/components'
import { cn } from '@/lib/cn'
import { AddressFields, LocationPicker, type AddressFieldValues, type LatLng } from '@/lib/maps'
import { ApiError } from '@/lib/api'
import { useCreateAddress, useMyAddresses } from '../hooks/useAddresses'

interface LocationStepProps {
  addressId: string | null
  onChange: (addressId: string) => void
  onNext: () => void
  onBack: () => void
}

const EMPTY_ADDRESS: AddressFieldValues = {
  street: '',
  unit: '',
  city: '',
  province: '',
  postal_code: '',
}

export function LocationStep({ addressId, onChange, onNext, onBack }: LocationStepProps) {
  const { data: addresses, isLoading } = useMyAddresses()
  const { mutateAsync: saveAddress, isPending: isSaving } = useCreateAddress()
  const [isAddingNew, setIsAddingNew] = useState(false)
  const [fields, setFields] = useState<AddressFieldValues>(EMPTY_ADDRESS)
  const [coords, setCoords] = useState<LatLng | null>(null)

  const mapAddress = [fields.street, fields.city, fields.province].filter(Boolean).join(', ')

  const handleSaveNewAddress = async () => {
    if (!coords) {
      toast.error('Drag the pin or locate your address on the map first.')
      return
    }
    try {
      const address = await saveAddress({
        street: fields.street,
        unit: fields.unit || null,
        city: fields.city,
        state_province: fields.province,
        postal_code: fields.postal_code,
        lat: coords.lat,
        lng: coords.lng,
      })
      onChange(address.id)
      setIsAddingNew(false)
      setFields(EMPTY_ADDRESS)
      setCoords(null)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save this address.')
    }
  }

  if (isLoading) return null

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-base font-semibold text-gray-900">Service location</h2>

      {!isAddingNew && (
        <div className="flex flex-col gap-2">
          {(addresses ?? []).map((address) => (
            <label
              key={address.id}
              className={cn(
                'flex cursor-pointer items-start gap-3 rounded-md border p-3',
                addressId === address.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200',
              )}
            >
              <input
                type="radio"
                name="address"
                className="mt-1"
                checked={addressId === address.id}
                onChange={() => onChange(address.id)}
              />
              <span className="text-sm">
                <span className="font-medium text-gray-900">{address.label ?? 'Address'}</span>
                <br />
                {address.street}
                {address.unit ? `, ${address.unit}` : ''}, {address.city}, {address.state_province}{' '}
                {address.postal_code}
              </span>
            </label>
          ))}
          {(addresses ?? []).length === 0 && (
            <p className="text-sm text-gray-500">No saved addresses yet. Add one below.</p>
          )}
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => setIsAddingNew(true)}
            className="self-start"
          >
            + Add a new address
          </Button>
        </div>
      )}

      {isAddingNew && (
        <Card className="flex flex-col gap-4">
          <AddressFields values={fields} onChange={setFields} />
          <LocationPicker
            address={mapAddress}
            onAddressChange={() => {}}
            addressReadOnly
            coords={coords}
            onCoordsChange={setCoords}
          />
          <div className="flex gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={() => setIsAddingNew(false)}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="button"
              isLoading={isSaving}
              onClick={handleSaveNewAddress}
              className="flex-1"
            >
              Save address
            </Button>
          </div>
        </Card>
      )}

      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button
          type="button"
          disabled={!addressId || isAddingNew}
          onClick={onNext}
          className="flex-1"
        >
          Continue
        </Button>
      </div>
    </div>
  )
}
