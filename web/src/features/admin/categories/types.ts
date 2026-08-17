export interface Category {
  id: string
  name: string
  slug: string
  icon: string | null
  is_active: boolean
  sort_order: number
  /** Present on create/update responses; the tree GET may omit it. */
  platform_fee_percentage?: string | number
  children: Category[]
}

export interface CategoryPayload {
  parent_id?: string | null
  name?: string
  slug?: string
  icon?: string | null
  is_active?: boolean
  sort_order?: number
  platform_fee_percentage?: string | number
}

export interface ReorderEntry {
  id: string
  sort_order: number
}
