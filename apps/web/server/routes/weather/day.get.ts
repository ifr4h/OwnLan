import {
  extractUkPostcode,
  snapshotForLessonStart,
  weatherHint,
  type GeoPoint,
  type WeatherKind,
  type WeatherSnapshot,
} from '~/utils/weather/openMeteo'

type HourlyBundle = {
  time: string[]
  weather_code: number[]
  temperature_2m: number[]
  precipitation_probability: number[]
  wind_speed_10m: number[]
}

const UK_FALLBACK: GeoPoint = { lat: 52.4862, lng: -1.8904 }
const WINDY_KMH = 40

/**
 * Same-origin weather proxy so Today chips work even when the Yii API
 * cannot reach Open-Meteo (or Brave blocks browser third-party calls).
 *
 * GET /weather/day?date=YYYY-MM-DD&timezone=Europe/London&address=...
 * Optional: &starts=2026-09-06T10:00,2026-09-06T18:00
 */
export default defineEventHandler(async (event) => {
  const q = getQuery(event)
  const date = String(q.date || '').slice(0, 10)
  const timezone = String(q.timezone || 'Europe/London')
  const address = String(q.address || '')
  const starts = String(q.starts || '')
    .split(',')
    .map(s => s.trim())
    .filter(Boolean)

  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    throw createError({ statusCode: 400, statusMessage: 'date required (YYYY-MM-DD)' })
  }

  const point = (await geocode(address)) ?? UK_FALLBACK
  const hourly = await fetchHourly(point, date, timezone)
  if (!hourly) {
    return { ok: false, by_start: {} as Record<string, WeatherSnapshot> }
  }

  const targets = starts.length
    ? starts
    : hourly.time

  const by_start: Record<string, WeatherSnapshot> = {}
  for (const start of targets) {
    const snap = snapshotForLessonStart(hourly, start)
    if (snap) {
      by_start[start.slice(0, 16)] = snap
    }
  }

  // Also expose every hour so clients can match flexibly.
  for (const t of hourly.time) {
    const key = t.slice(0, 16)
    if (!by_start[key]) {
      const snap = snapshotFromHourly(hourly, t)
      if (snap) by_start[key] = snap
    }
  }

  return { ok: true, by_start }
})

async function geocode(address: string): Promise<GeoPoint | null> {
  const query = address.trim()
  if (!query) return null

  const postcode = extractUkPostcode(query)
  if (postcode) {
    const compact = postcode.replace(/\s+/g, '')
    try {
      const res = await fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(compact)}`)
      if (res.ok) {
        const data = await res.json() as { result?: { latitude?: number; longitude?: number } }
        if (typeof data.result?.latitude === 'number' && typeof data.result?.longitude === 'number') {
          return { lat: data.result.latitude, lng: data.result.longitude }
        }
      }
    } catch {
      // continue
    }
  }

  const locality = query.split(',').map(p => p.trim()).filter(Boolean).reverse()
    .find(p => !/^\d+\s/.test(p) && p.length >= 3)
  if (!locality) return null

  try {
    const url = new URL('https://geocoding-api.open-meteo.com/v1/search')
    url.searchParams.set('name', locality)
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

async function fetchHourly(
  point: GeoPoint,
  date: string,
  timezone: string,
): Promise<HourlyBundle | null> {
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
    return data.hourly
  } catch {
    return null
  }
}

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
  if (windy && kind !== 'clear' && kind !== 'windy') return `${base[kind]} and windy`
  if (windy && kind === 'clear') return 'bright and windy'
  return base[kind]
}

function snapshotFromHourly(hourly: HourlyBundle, time: string): WeatherSnapshot | null {
  const idx = hourly.time.indexOf(time)
  if (idx < 0) return null
  const code = hourly.weather_code[idx] ?? 3
  const wind = hourly.wind_speed_10m[idx] ?? 0
  const windy = wind >= WINDY_KMH
  let kind = kindFromCode(code)
  if (windy && kind === 'clear' && wind >= 55) kind = 'windy'
  const label = labelFor(kind, windy)
  const temperature_c = hourly.temperature_2m[idx] ?? null
  const snap: WeatherSnapshot = {
    kind,
    label,
    hint: '',
    temperature_c,
    precipitation_probability: hourly.precipitation_probability[idx] ?? null,
    windy,
    hour: time,
  }
  snap.hint = weatherHint(snap)
  return snap
}
