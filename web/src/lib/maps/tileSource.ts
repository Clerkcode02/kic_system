const MAPTILER_KEY_PLACEHOLDER = '__MAPTILER_KEY__'

function clean(value: string | undefined): string {
  return (value ?? '').trim()
}

/**
 * Resolves the configured tile endpoint, or null when the app has no usable
 * tile source. Returning null rather than an empty string matters: Leaflet
 * treats `url=""` as a relative template, so every tile request resolves back
 * to the SPA's own HTML and the map silently renders as a grey box with only
 * markers and controls drawn on top.
 *
 * Tiles come from MapTiler/Stadia only — raw tile.openstreetmap.org is
 * disallowed in this project (CLAUDE.md §2).
 */
export function resolveTileUrl(): string | null {
  const url = clean(import.meta.env.VITE_MAP_TILE_URL)
  if (url === '') return null

  if (url.includes(MAPTILER_KEY_PLACEHOLDER)) {
    const key = clean(import.meta.env.VITE_MAPTILER_API_KEY)
    if (key === '') return null
    return url.replaceAll(MAPTILER_KEY_PLACEHOLDER, key)
  }

  return url
}

export function resolveTileAttribution(): string {
  return clean(import.meta.env.VITE_MAP_TILE_ATTRIBUTION)
}
