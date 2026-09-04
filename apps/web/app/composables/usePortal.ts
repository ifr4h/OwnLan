import { apiFetch } from '~/composables/useApi'
import { formatSyncedAt } from '~/utils/portalFormat'

export type PortalLesson = {
  id: number
  starts_at?: string
  starts_at_display: string
  starts_at_day?: string
  starts_at_time: string
  ends_at_time: string
  duration_minutes: number
  duration_label?: string
  pickup_address: string | null
  pickup_short?: string | null
  status: string
  learner_summary: string | null
  next_focus: string | null
  skills?: string[]
}

export type PortalPriority = {
  kind:
    | 'lesson_today'
    | 'lesson_tomorrow'
    | 'lesson_upcoming'
    | 'test_approaching'
    | 'new_recap'
    | 'package_low'
    | 'no_booking'
    | 'insight'
  lesson_id?: number
}

export type PortalProgressSummary = {
  confident: number
  developing: number
  practising: number
  introduced: number
  skills_with_rating: number
}

export type PortalPackageBalance = {
  credit_minutes: number
  credit_label: string
  credit_hours?: number
  has_credit: boolean
  purchased_minutes?: number
  purchased_hours?: number
  used_minutes?: number
  used_hours?: number
  used_percent?: number
  amount_due_pence: number
  amount_due_label: string
  has_amount_due: boolean
}

export type PortalJourneyStats = {
  lessons_completed: number
  total_minutes: number
  total_hours: number
  total_hours_label: string
  average_duration_minutes: number
  hours_this_month: number
  hours_this_month_label: string
  started_on: string | null
  started_on_display: string | null
}

export type PortalRecap = {
  lesson_id: number
  date_label: string
  is_today?: boolean
  duration_minutes: number
  duration_label: string
  skills: string[]
  learner_summary: string | null
  next_focus: string | null
  route_id?: number | null
}

export type PortalTheory = {
  status: string
  label: string
  pass_date: string | null
  expires_on: string | null
  expires_on_display: string | null
  days_until_expiry: number | null
  urgency: string | null
}

export type PortalPracticalTest = {
  test_date: string
  test_date_display: string
  test_centre: string | null
  countdown_label: string
  days_until: number
  lessons_booked_before_test: number
}

export type PortalHome = {
  learner: { first_name: string; full_name: string }
  greeting: string
  instructor: {
    display_name: string
    business_name: string
    contact_phone: string | null
    contact_email: string | null
    service_area: string | null
    cancellation_policy: string | null
  }
  priority: PortalPriority
  next_lesson: PortalLesson | null
  upcoming_lessons: PortalLesson[]
  previous_lessons: PortalLesson[]
  progress: {
    last_lesson_summary: string | null
    next_focus: string | null
    summary: PortalProgressSummary
    insights: string[]
  }
  journey: PortalJourneyStats
  practical_test: PortalPracticalTest | null
  theory: PortalTheory | null
  package_and_balance: PortalPackageBalance
  routes: { count: number }
  insights: string[]
  recent_recap: PortalRecap | null
  booking?: {
    can_book: boolean
    booking_mode: string
    reschedule_mode: string
    can_cancel: boolean
    cta_label: string | null
    cta_path: string | null
    open_request?: {
      id: number
      status: string
      starts_at_day: string
      starts_at_time: string
    } | null
  }
  synced_at: string
}

export type PortalHoursMonth = {
  month: string
  label: string
  minutes: number
  hours: number
  lesson_ids: number[]
}

export type PortalHistoryItem = {
  id: number
  date_key: string
  date_label: string
  title: string
  duration_minutes: number
  duration_label: string
  learner_summary: string | null
  next_focus: string | null
  skills: string[]
}

export type PortalJourney = {
  stats: PortalJourneyStats
  started_on: string | null
  started_on_display: string | null
  current_focus: string | null
  history: PortalHistoryItem[]
  hours_by_month: PortalHoursMonth[]
}

export type PortalSkillHistoryPoint = {
  rating: string
  rank: number
  recorded_at: string
  lesson_id: number | null
}

export type PortalSkill = {
  id: number
  code: string
  label: string
  rating: string | null
  rating_label: string | null
  recorded_at: string | null
  history: PortalSkillHistoryPoint[]
}

export type PortalSkillCategory = {
  code: string
  label: string
  skills: PortalSkill[]
}

export type PortalPractisedCount = {
  skill_id: number
  code: string
  label: string
  category_label: string
  lesson_count: number
}

export type PortalProgress = {
  summary: PortalProgressSummary
  categories: PortalSkillCategory[]
  practised_counts: PortalPractisedCount[]
  insights: string[]
  booking?: {
    can_book: boolean
    booking_mode: string
    reschedule_mode: string
    can_cancel: boolean
    cta_label: string | null
    cta_path: string | null
    open_request?: {
      id: number
      status: string
      starts_at_day: string
      starts_at_time: string
    } | null
  }
}

export type PortalMoneyActivity = {
  id: string
  type: 'lesson' | 'package_added' | string
  date_label: string
  label: string
  minutes_delta: number
  minutes_label: string
}

export type PortalMoney = {
  credit: PortalPackageBalance
  activity: PortalMoneyActivity[]
  payments?: import('~/composables/useOnlinePayments').PortalPaymentsContext
}

export type PortalPrepare = {
  next_lesson: PortalLesson | null
  focus: string | null
  last_lesson_id: number | null
  last_route_id: number | null
}

export type PortalRouteBounds = {
  north: number
  south: number
  east: number
  west: number
} | null

export type PortalRouteSummary = {
  id: number
  lesson_id: number
  date_label: string
  started_at: string | null
  duration_seconds: number | null
  duration_label: string | null
  distance_metres: number | null
  distance_label: string | null
  label: string | null
  skills: string[]
  bounds: PortalRouteBounds
}

export type PortalRouteDetail = PortalRouteSummary & {
  encoded_polyline: string | null
  learner_summary: string | null
  next_focus: string | null
  skills_detail: Array<{
    id: number
    code: string
    label: string
    category_code?: string
    category_label?: string
  }>
  moments?: Array<{
    id: number
    offset_seconds: number | null
    offset_label: string | null
    lat: number
    lng: number
    kind: string
    label: string | null
    learner_note: string | null
  }>
}

export type PortalRoutesList = {
  items: PortalRouteSummary[]
}

type CacheEnvelope<T> = {
  learnerId: number
  syncedAt: string
  data: T
}

const HOME_CACHE_PREFIX = 'ownlane.portal.home.'
const SYNC_CACHE_PREFIX = 'ownlane.portal.sync.'

function homeCacheKey(learnerId: number) {
  return `${HOME_CACHE_PREFIX}${learnerId}`
}

function syncCacheKey(learnerId: number) {
  return `${SYNC_CACHE_PREFIX}${learnerId}`
}

function readJson<T>(key: string): T | null {
  if (!import.meta.client) return null
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return null
    return JSON.parse(raw) as T
  } catch {
    return null
  }
}

function writeJson(key: string, value: unknown) {
  if (!import.meta.client) return
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    // Quota / private mode — ignore.
  }
}

export function usePortal() {
  const { me } = usePortalAuth()
  const online = useState<boolean>('portal-online', () => true)
  const stale = useState<boolean>('portal-stale', () => false)
  const lastSyncAt = useState<string | null>('portal-last-sync', () => null)

  const learnerId = computed(() => me.value?.learner.id ?? null)

  function bindConnectivity() {
    if (!import.meta.client) return
    online.value = navigator.onLine
    const onOnline = () => {
      online.value = true
      stale.value = false
    }
    const onOffline = () => {
      online.value = false
    }
    window.addEventListener('online', onOnline)
    window.addEventListener('offline', onOffline)
    return () => {
      window.removeEventListener('online', onOnline)
      window.removeEventListener('offline', onOffline)
    }
  }

  function cacheHome(data: PortalHome) {
    const id = learnerId.value
    if (!id) return
    const syncedAt = data.synced_at || new Date().toISOString()
    writeJson(homeCacheKey(id), { learnerId: id, syncedAt, data } satisfies CacheEnvelope<PortalHome>)
    writeJson(syncCacheKey(id), syncedAt)
    lastSyncAt.value = syncedAt
    stale.value = false
  }

  function readCachedHome(): PortalHome | null {
    const id = learnerId.value
    if (!id) return null
    const envelope = readJson<CacheEnvelope<PortalHome>>(homeCacheKey(id))
    if (!envelope || envelope.learnerId !== id) return null
    lastSyncAt.value = envelope.syncedAt
    return envelope.data
  }

  function readLastSyncLabel(): string {
    const id = learnerId.value
    const iso = lastSyncAt.value
      ?? (id ? readJson<string>(syncCacheKey(id)) : null)
    return formatSyncedAt(iso)
  }

  async function fetchHome(opts?: { allowStale?: boolean }): Promise<PortalHome> {
    const allowStale = opts?.allowStale !== false
    try {
      const data = await apiFetch<PortalHome>('/portal/home')
      cacheHome(data)
      online.value = true
      return data
    } catch (e) {
      if (allowStale) {
        const cached = readCachedHome()
        if (cached) {
          stale.value = true
          online.value = typeof navigator !== 'undefined' ? navigator.onLine : false
          return cached
        }
      }
      throw e
    }
  }

  async function fetchJourney(): Promise<PortalJourney> {
    return await apiFetch<PortalJourney>('/portal/journey')
  }

  async function fetchProgress(): Promise<PortalProgress> {
    return await apiFetch<PortalProgress>('/portal/progress')
  }

  async function fetchMoney(): Promise<PortalMoney> {
    return await apiFetch<PortalMoney>('/portal/money')
  }

  async function fetchPrepare(): Promise<PortalPrepare> {
    return await apiFetch<PortalPrepare>('/portal/prepare')
  }

  async function fetchRecap(lessonId: number): Promise<PortalRecap> {
    return await apiFetch<PortalRecap>(`/portal/lessons/${lessonId}/recap`)
  }

  async function fetchRoutes(): Promise<PortalRoutesList> {
    return await apiFetch<PortalRoutesList>('/portal/routes')
  }

  async function fetchRoute(routeId: number): Promise<PortalRouteDetail> {
    return await apiFetch<PortalRouteDetail>(`/portal/routes/${routeId}`)
  }

  return {
    online,
    stale,
    lastSyncAt,
    learnerId,
    bindConnectivity,
    cacheHome,
    readCachedHome,
    readLastSyncLabel,
    fetchHome,
    fetchJourney,
    fetchProgress,
    fetchMoney,
    fetchPrepare,
    fetchRecap,
    fetchRoutes,
    fetchRoute,
  }
}
