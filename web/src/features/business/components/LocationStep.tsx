import { useState } from 'react'
import toast from 'react-hot-toast'
import { Button } from '@/components'
import { AddressFields, LocationPicker, type AddressFieldValues, type LatLng } from '@/lib/maps'
import { ApiError } from '@/lib/api'
import { useUpdateBusinessProfile } from '../hooks/useBusiness'
import type { Business } from '../types'

interface LocationStepProps {
  business: Business
  onNext: () => void
  onBack: () => void
}

export function LocationStep({ business, onNext, onBack }: LocationStepProps) {
  const [address, setAddress] = useState<AddressFieldValues>({
    street: business.address.street ?? '',
    unit: business.address.unit ?? '',
    city: business.address.city ?? '',
    province: business.address.province ?? '',
    postal_code: business.address.postal_code ?? '',
  })
  const [coords, setCoords] = useState<LatLng | null>(business.location)
  const { mutateAsync, isPending } = useUpdateBusinessProfile()

  const mapAddress = [address.street, address.city, address.province].filter(Boolean).join(', ')

  const handleSubmit = async () => {
    if (!coords) {
      toast.error('Drag the pin or locate your address on the map first.')
      return
    }
    try {
      await mutateAsync({
        street: address.street,
        unit: address.unit || null,
        city: address.city,
        province: address.province,
        postal_code: address.postal_code,
        lat: coords.lat,
        lng: coords.lng,
      })
      onNext()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : 'Could not save your location.')
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <AddressFields values={address} onChange={setAddress} />
      <LocationPicker
        address={mapAddress}
        onAddressChange={() => {}}
        addressReadOnly
        coords={coords}
        onCoordsChange={setCoords}
      />
      <div className="flex gap-3">
        <Button type="button" variant="secondary" onClick={onBack} className="flex-1">
          Back
        </Button>
        <Button type="button" isLoading={isPending} onClick={handleSubmit} className="flex-1">
          Continue
        </Button>
      </div>
    </div>
  )
}
