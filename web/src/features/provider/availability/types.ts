export interface WeeklyAvailability {
  id: string
  day_of_week: number
  start_time: string
  end_time: string
  is_active: boolean
}

export interface AvailabilityOverride {
  id: string
  date: string
  is_blackout: boolean
  start_time: string | null
  end_time: string | null
}

export interface AvailabilityConfig {
  weekly: WeeklyAvailability[]
  overrides: AvailabilityOverride[]
}

export interface WeeklyAvailabilityInput {
  day_of_week: number
  start_time: string
  end_time: string
  is_active?: boolean
}

export interface AvailabilityOverrideInput {
  date: string
  is_blackout?: boolean
  start_time?: string | null
  end_time?: string | null
}

export interface ReplaceAvailabilityPayload {
  weekly?: WeeklyAvailabilityInput[]
  overrides?: AvailabilityOverrideInput[]
}
