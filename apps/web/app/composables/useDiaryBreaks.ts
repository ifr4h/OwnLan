import { formatDuration, parseHm } from '~/utils/calendar/timeGrid'

export type DiaryBreak = {
  id: string
  date: string
  start_minutes: number
  end_minutes: number
  label: string
}

const STORAGE_PREFIX = 'ownlane.diary.breaks.'

function storageKey(orgId: number | string | null | undefined): string {
  return `${STORAGE_PREFIX}${orgId ?? 'local'}`
}

export function useDiaryBreaks() {
  const { me } = useAuth()
  const breaks = useState<DiaryBreak[]>('diary-breaks', () => [])

  const orgId = computed(() => me.value?.organisation?.id ?? null)

  function load() {
    if (!import.meta.client) return
    try {
      const raw = localStorage.getItem(storageKey(orgId.value))
      breaks.value = raw ? (JSON.parse(raw) as DiaryBreak[]) : []
    } catch {
      breaks.value = []
    }
  }

  function persist() {
    if (!import.meta.client) return
    localStorage.setItem(storageKey(orgId.value), JSON.stringify(breaks.value))
  }

  function breaksForDates(dates: string[]): DiaryBreak[] {
    const set = new Set(dates)
    return breaks.value.filter(b => set.has(b.date))
  }

  function addBreak(input: {
    date: string
    starts_at_local: string
    duration_minutes: number
    label?: string
  }): DiaryBreak {
    const start = parseHm(input.starts_at_local.slice(11, 16) || input.starts_at_local)
    const end = start + Math.max(15, input.duration_minutes)
    const row: DiaryBreak = {
      id: `brk_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
      date: input.date,
      start_minutes: start,
      end_minutes: end,
      label: (input.label || 'Break').trim() || 'Break',
    }
    breaks.value = [...breaks.value, row]
    persist()
    return row
  }

  function removeBreak(id: string) {
    breaks.value = breaks.value.filter(b => b.id !== id)
    persist()
  }

  function labelForBreak(b: DiaryBreak): string {
    return `${b.label} · ${formatDuration(b.end_minutes - b.start_minutes)}`
  }

  watch(orgId, () => load(), { immediate: true })

  return {
    breaks,
    load,
    breaksForDates,
    addBreak,
    removeBreak,
    labelForBreak,
  }
}
