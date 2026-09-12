/**
 * Open-Meteo forecast helpers for Today lesson weather chips.
 * Best-effort only — never block teaching if weather is unavailable.
 */

export type WeatherKind =
  | 'clear'
  | 'partly_cloudy'
  | 'cloudy'
  | 'fog'
  | 'drizzle'
  | 'light_rain'
  | 'rain'
  | 'heavy_rain'
  | 'wintry'
  | 'thunder'
  | 'windy'

export type WeatherSnapshot = {
  kind: WeatherKind
  label: string
  hint: string
  temperature_c: number | null
  precipitation_probability: number | null
  windy: boolean
  hour: string
}

export type GeoPoint = { lat: number; lng: number }

type HourlyBundle = {
  time: string[]
  weather_code: number[]
  temperature_2m: number[]
  precipitation_probability: number[]
  wind_speed_10m: number[]
}

const WINDY_KMH = 40
const CACHE_PREFIX = 'ol.weather.v1.'
const UK_POSTCODE_RE = /\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/i
/** Europe/London fallback when we have no usable address (central England). */
const UK_FALLBACK: GeoPoint = { lat: 52.4862, lng: -1.8904 }

function kindFromCode(code: number): WeatherKind {
  if (code === 0) return 'clear'
  if (code <= 2) return 'partly_cloudy'
  if (code === 3) return 'cloudy'
  if (code === 45 || code === 48) return 'fog'
  if (code >= 51 && code <= 57) return 'drizzle'
  if (code === 61 || code === 80) return 'light_rain'
  if (code === 63 || code === 81) return 'rain'
  if (code === 65 || code === 82) return 'heavy_rain'
  if ((code >= 66 && code <= 67) || (code >= 71 && code <= 77) || code === 85 || code === 86) {
    return 'wintry'
  }
  if (code >= 95) return 'thunder'
  return 'cloudy'
}

function labelFor(kind: WeatherKind, windy: boolean): string {
  const base: Record<WeatherKind, string> = {
    clear: 'bright sunshine',
    partly_cloudy: 'partly cloudy',
    cloudy: 'cloudy',
    fog: 'foggy',
    drizzle: 'drizzle',
    light_rain: 'light rain',
    rain: 'rain',
    heavy_rain: 'heavy rain',
    wintry: 'wintry',
    thunder: 'thundery showers',
    windy: 'windy',
  }
  if (windy && kind !== 'clear' && kind !== 'windy') {
    return `${base[kind]} and windy`
  }
  if (windy && kind === 'clear') return 'bright and windy'
  return base[kind]
}

export function weatherHint(snapshot: WeatherSnapshot): string {
  let line = `During this lesson, it might be ${snapshot.label}`
  if (snapshot.temperature_c != null && Number.isFinite(snapshot.temperature_c)) {
    line += `, around ${Math.round(snapshot.temperature_c)}°C`
  }
  return `${line}.`
}

function snapshotAtHour(hourly: HourlyBundle, isoLocalHour: string): WeatherSnapshot | null {
  const hourPrefix = isoLocalHour.replace(' ', 'T').slice(0, 13)
  const idx = hourly.time.findIndex((t) => {
    const normalised = t.replace(' ', 'T')
    return normalised === `${hourPrefix}:00` || normalised.startsWith(hourPrefix)
  })
  if (idx < 0) return null

  const code = hourly.weather_code[idx] ?? 3
  const wind = hourly.wind_speed_10m[idx] ?? 0
  const windy = wind >= WINDY_KMH
  let kind = kindFromCode(code)
  if (windy && kind === 'clear' && wind >= 55) kind = 'windy'

  const label = labelFor(kind, windy)
  const temperature_c = hourly.temperature_2m[idx] ?? null
  const precipitation_probability = hourly.precipitation_probability[idx] ?? null

  return {
    kind,
    label,
    hint: '',
    temperature_c,
    precipitation_probability,
    windy,
    hour: hourly.time[idx] ?? isoLocalHour,
  }
}

function cacheKey(lat: number, lng: number, date: string): string {
  return `${CACHE_PREFIX}${date}:${lat.toFixed(2)},${lng.toFixed(2)}`
}

function readCache(key: string): HourlyBundle | null {
  if (!import.meta.client) return null
  try {
    const raw = sessionStorage.getItem(key)
    if (!raw) return null
    return JSON.parse(raw) as HourlyBundle
  } catch {
    return null
  }
}

function writeCache(key: string, hourly: HourlyBundle): void {
  if (!import.meta.client) return
  try {
    sessionStorage.setItem(key, JSON.stringify(hourly))
  } catch {
    // ignore quota
  }
}

export function extractUkPostcode(address: string): string | null {
  const match = address.match(UK_POSTCODE_RE)
  return match?.[1]?.replace(/\s+/g, ' ').toUpperCase() ?? null
}

export async function resolveApproxPoint(pickupAddresses: string[]): Promise<GeoPoint | null> {
  if (!import.meta.client) return null

  for (const address of pickupAddresses) {
    const point = await geocodeAddress(address)
    if (point) return point
  }

  const geo = await tryBrowserGeo()
  if (geo) return geo

  // Last resort so Today still shows a forecast chip for UK instructors.
  return UK_FALLBACK
}

function tryBrowserGeo(): Promise<GeoPoint | null> {
  return new Promise((resolve) => {
    if (!navigator.geolocation) {
      resolve(null)
      return
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => resolve(null),
      { enableHighAccuracy: false, timeout: 2500, maximumAge: 30 * 60 * 1000 },
    )
  })
}

async function geocodeAddress(address: string): Promise<GeoPoint | null> {
  const query = address.trim()
  if (!query) return null

  const postcode = extractUkPostcode(query)
  if (postcode) {
    const fromPostcode = await geocodeUkPostcode(postcode)
    if (fromPostcode) return fromPostcode
  }

  // Try locality fragments (comma parts), skipping bare house-number streets first.
  const parts = query
    .split(',')
    .map(p => p.trim())
    .filter(Boolean)

  const candidates = [
    ...parts.slice().reverse().filter(p => !/^\d+\s/.test(p)),
    ...parts,
  ]

  const seen = new Set<string>()
  for (const name of candidates) {
    const key = name.toLowerCase()
    if (seen.has(key) || name.length < 3) continue
    seen.add(key)
    const hit = await geocodePlaceName(name)
    if (hit) return hit
  }

  return null
}

/** Free UK postcode → lat/lng (no API key). */
async function geocodeUkPostcode(postcode: string): Promise<GeoPoint | null> {
  try {
    const compact = postcode.replace(/\s+/g, '')
    const res = await fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(compact)}`)
    if (!res.ok) return null
    const data = await res.json() as {
      status?: number
      result?: { latitude?: number; longitude?: number }
    }
    const lat = data.result?.latitude
    const lng = data.result?.longitude
    if (typeof lat !== 'number' || typeof lng !== 'number') return null
    return { lat, lng }
  } catch {
    return null
  }
}

async function geocodePlaceName(name: string): Promise<GeoPoint | null> {
  try {
    const url = new URL('https://geocoding-api.open-meteo.com/v1/search')
    url.searchParams.set('name', name)
    url.searchParams.set('count', '1')
    url.searchParams.set('language', 'en')
    url.searchParams.set('countryCode', 'GB')

    const res = await fetch(url.toString())
    if (!res.ok) return null
    const data = await res.json() as { results?: Array<{ latitude: number; longitude: number }> }
    const hit = data.results?.[0]
    if (!hit) return null
    return { lat: hit.latitude, lng: hit.longitude }
  } catch {
    return null
  }
}

export async function fetchDayHourly(
  point: GeoPoint,
  date: string,
  timezone: string,
): Promise<HourlyBundle | null> {
  const key = cacheKey(point.lat, point.lng, date)
  const cached = readCache(key)
  if (cached) return cached

  try {
    const url = new URL('https://api.open-meteo.com/v1/forecast')
    url.searchParams.set('latitude', String(point.lat))
    url.searchParams.set('longitude', String(point.lng))
    url.searchParams.set(
      'hourly',
      'weather_code,temperature_2m,precipitation_probability,wind_speed_10m',
    )
    url.searchParams.set('timezone', timezone || 'Europe/London')
    url.searchParams.set('start_date', date)
    url.searchParams.set('end_date', date)

    const res = await fetch(url.toString())
    if (!res.ok) return null
    const data = await res.json() as { hourly?: HourlyBundle }
    if (!data.hourly?.time?.length) return null

    writeCache(key, data.hourly)
    return data.hourly
  } catch {
    return null
  }
}

/** Map a lesson start (ISO local or UTC) to the matching hourly snapshot. */
export function snapshotForLessonStart(
  hourly: HourlyBundle,
  startsAtLocal: string,
): WeatherSnapshot | null {
  const snap = snapshotAtHour(hourly, startsAtLocal)
  if (!snap) {
    // Fallback: if local parse failed (e.g. UTC ISO), try matching by hour token only.
    const hourMatch = startsAtLocal.match(/T(\d{2}):/)
    if (hourMatch) {
      const hour = hourMatch[1]
      const idx = hourly.time.findIndex(t => t.includes(`T${hour}:`))
      if (idx >= 0) {
        const retry = snapshotAtHour(hourly, hourly.time[idx]!)
        if (retry) {
          retry.hint = weatherHint(retry)
          return retry
        }
      }
    }
    return null
  }
  snap.hint = weatherHint(snap)
  return snap
}
