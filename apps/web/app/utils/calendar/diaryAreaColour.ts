import { ukOutwardCode } from '~/utils/ukPostcode'

export type DiaryColourMode = 'payment' | 'area'

export type DiaryArea = {
  key: string
  label: string
  /** Stable 0–7 slot into `--color-diary-area-N` tokens. */
  slot: number
}

const AREA_SLOT_COUNT = 8
const NO_PLACE_KEY = 'no-place'

/** Soft pastel slots — kept distinct from payment lime/coral/blue. */
export const DIARY_AREA_SLOT_COUNT = AREA_SLOT_COUNT

function hashKey(key: string): number {
  let h = 0
  for (let i = 0; i < key.length; i += 1) {
    h = ((h << 5) - h) + key.charCodeAt(i)
    h |= 0
  }
  return Math.abs(h)
}

function slotForKey(key: string): number {
  return hashKey(key) % AREA_SLOT_COUNT
}

/** Town / village fragment from a free-text UK address. */
function placeLabel(address: string): string | null {
  const cleaned = address.replace(/\s+/g, ' ').trim()
  if (!cleaned) return null

  const first = cleaned.split(',')[0]?.trim() ?? cleaned
  let place = first.replace(/^\d+[a-z]?\s+/i, '').trim()
  if (!place) return null
  // Drop lone outward codes used as the whole "street" line.
  if (/^[A-Z]{1,2}\d[A-Z\d]?$/i.test(place)) return null
  if (place.length > 22) place = `${place.slice(0, 20)}…`
  return place
}

/**
 * Working area for diary colouring.
 * Prefer UK outward code (MK3, SW9) so nearby pickups share a colour;
 * fall back to the first place name; otherwise "No place".
 */
export function resolveDiaryArea(pickupAddress: string | null | undefined): DiaryArea {
  const address = pickupAddress?.trim() ?? ''
  if (!address) {
    return { key: NO_PLACE_KEY, label: 'No place', slot: slotForKey(NO_PLACE_KEY) }
  }

  const outcode = ukOutwardCode(address)
  if (outcode) {
    const place = placeLabel(address)
    const label = place && !place.toUpperCase().includes(outcode)
      ? `${place} · ${outcode}`
      : outcode
    return { key: `pc:${outcode}`, label, slot: slotForKey(`pc:${outcode}`) }
  }

  const place = placeLabel(address)
  if (place) {
    const key = `place:${place.toLowerCase()}`
    return { key, label: place, slot: slotForKey(key) }
  }

  return { key: NO_PLACE_KEY, label: 'No place', slot: slotForKey(NO_PLACE_KEY) }
}

/** Unique areas in diary order of first appearance, for the filter menu. */
export function collectDiaryAreas(
  pickups: Array<string | null | undefined>,
): DiaryArea[] {
  const seen = new Map<string, DiaryArea>()
  for (const pickup of pickups) {
    const area = resolveDiaryArea(pickup)
    if (!seen.has(area.key)) seen.set(area.key, area)
  }
  return [...seen.values()].sort((a, b) => {
    if (a.key === NO_PLACE_KEY) return 1
    if (b.key === NO_PLACE_KEY) return -1
    return a.label.localeCompare(b.label, 'en-GB')
  })
}

export function readDiaryColourMode(): DiaryColourMode {
  if (!import.meta.client) return 'payment'
  try {
    const raw = localStorage.getItem('ownlane.diary.colourMode')
    return raw === 'area' ? 'area' : 'payment'
  } catch {
    return 'payment'
  }
}

export function writeDiaryColourMode(mode: DiaryColourMode) {
  if (!import.meta.client) return
  try {
    localStorage.setItem('ownlane.diary.colourMode', mode)
  } catch {
    // ignore
  }
}
