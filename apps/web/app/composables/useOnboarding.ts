export type OnboardingChecklistItem = {
  id: string
  label: string
  done: boolean
}

export type OnboardingStatus = {
  stage: 'setup' | 'add_pupils' | 'book_lesson' | 'use_today' | 'done'
  next_step: string
  pupil_count: number
  lesson_count: number
  upcoming_lesson_count: number
  suggest_business_confirm: boolean
  business_name?: string
  display_name?: string | null
  checklist: OnboardingChecklistItem[]
}

type DismissedTips = {
  business_confirm?: boolean
  needs_you_intro?: boolean
  diary_gaps_intro?: boolean
  checklist?: boolean
}

const STORAGE_KEY = 'ownlane.onboarding.dismissed'

function readDismissed(): DismissedTips {
  if (!import.meta.client) return {}
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as DismissedTips
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function writeDismissed(next: DismissedTips) {
  if (!import.meta.client) return
  localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
}

export function useOnboarding() {
  const { me, fetchMe } = useAuth()
  const dismissed = useState<DismissedTips>('onboarding-dismissed', () => ({}))

  if (import.meta.client && Object.keys(dismissed.value).length === 0) {
    dismissed.value = readDismissed()
  }

  const onboarding = computed(() => me.value?.onboarding ?? null)

  const stage = computed(() => onboarding.value?.stage ?? 'done')

  const showBusinessConfirm = computed(() =>
    Boolean(onboarding.value?.suggest_business_confirm)
    && !dismissed.value.business_confirm
    && (stage.value === 'add_pupils' || stage.value === 'book_lesson'),
  )

  const showChecklist = computed(() =>
    Boolean(onboarding.value)
    && stage.value !== 'done'
    && !dismissed.value.checklist,
  )

  const showNeedsYouIntro = computed(() => !dismissed.value.needs_you_intro)

  const showDiaryGapsIntro = computed(() => !dismissed.value.diary_gaps_intro)

  function dismiss(tip: keyof DismissedTips) {
    dismissed.value = { ...dismissed.value, [tip]: true }
    writeDismissed(dismissed.value)
  }

  async function refresh() {
    await fetchMe()
  }

  return {
    onboarding,
    stage,
    showBusinessConfirm,
    showChecklist,
    showNeedsYouIntro,
    showDiaryGapsIntro,
    dismiss,
    refresh,
  }
}
