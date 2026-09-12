export type AuthUser = {
  id: number
  email: string
  name: string
}

export type AuthOrganisation = {
  id: number
  name: string
  timezone: string
  default_lesson_duration_minutes?: number
  default_hourly_rate_pence?: number | null
  work_days?: number[]
  work_start_time?: string
  work_end_time?: string
  week_starts_on?: number
}

export type AuthMembership = {
  role: string
}

export type AuthInstructor = {
  id: number
  display_name: string
}

export type MeResponse = {
  user: AuthUser
  organisation: AuthOrganisation | null
  membership: AuthMembership | null
  instructor: AuthInstructor | null
  onboarding?: import('./useOnboarding').OnboardingStatus
}

export function useAuth() {
  const me = useState<MeResponse | null>('auth-me', () => null)
  const ready = useState<boolean>('auth-ready', () => false)

  const isAuthenticated = computed(() => me.value !== null)

  async function api<T>(path: string, options: Parameters<typeof $fetch<T>>[1] = {}): Promise<T> {
    return await apiFetch<T>(path, options)
  }

  async function fetchMe(): Promise<MeResponse | null> {
    try {
      me.value = await api<MeResponse>('/auth/me')
      return me.value
    } catch {
      me.value = null
      return null
    } finally {
      ready.value = true
    }
  }

  async function register(payload: { name: string; email: string; password: string }) {
    me.value = await api<MeResponse>('/auth/register', {
      method: 'POST',
      body: payload,
    })
    ready.value = true
    return me.value
  }

  async function login(payload: { email: string; password: string }) {
    me.value = await api<MeResponse>('/auth/login', {
      method: 'POST',
      body: payload,
    })
    ready.value = true
    return me.value
  }

  async function logout() {
    try {
      await api('/auth/logout', { method: 'POST' })
    } finally {
      me.value = null
      ready.value = true
      // Allow a fresh client session check after explicit logout + re-login.
      const clientChecked = useState<boolean>('auth-client-checked', () => false)
      clientChecked.value = false
    }
  }

  return {
    me,
    ready,
    isAuthenticated,
    fetchMe,
    register,
    login,
    logout,
  }
}
