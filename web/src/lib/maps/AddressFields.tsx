import { Input, Select } from '@/components'
import { CANADIAN_PROVINCES, formatCanadianPostalCode } from './constants'

export interface AddressFieldValues {
  street: string
  unit?: string
  city: string
  province: string
  postal_code: string
}

interface AddressFieldsProps {
  values: AddressFieldValues
  errors?: Partial<Record<keyof AddressFieldValues, string>>
  onChange: (values: AddressFieldValues) => void
}

const provinceOptions = CANADIAN_PROVINCES.map((province) => ({
  value: province.code,
  label: province.name,
}))

/** Canadian address fields only — street/unit/city/province/postal code, no country field (CLAUDE.md §5). */
export function AddressFields({ values, errors, onChange }: AddressFieldsProps) {
  const set = <K extends keyof AddressFieldValues>(key: K, value: AddressFieldValues[K]) => {
    onChange({ ...values, [key]: value })
  }

  return (
    <div className="flex flex-col gap-4">
      <Input
        label="Street address"
        name="street"
        autoComplete="address-line1"
        value={values.street}
        error={errors?.street}
        onChange={(event) => set('street', event.target.value)}
      />
      <Input
        label="Unit / suite (optional)"
        name="unit"
        autoComplete="address-line2"
        value={values.unit ?? ''}
        error={errors?.unit}
        onChange={(event) => set('unit', event.target.value)}
      />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          label="City"
          name="city"
          autoComplete="address-level2"
          value={values.city}
          error={errors?.city}
          onChange={(event) => set('city', event.target.value)}
        />
        <Select
          label="Province / territory"
          name="province"
          placeholder="Select a province"
          options={provinceOptions}
          value={values.province}
          error={errors?.province}
          onChange={(event) => set('province', event.target.value)}
        />
      </div>
      <Input
        label="Postal code"
        name="postal_code"
        autoComplete="postal-code"
        placeholder="A1A 1A1"
        value={values.postal_code}
        error={errors?.postal_code}
        onChange={(event) => set('postal_code', formatCanadianPostalCode(event.target.value))}
      />
    </div>
  )
}
