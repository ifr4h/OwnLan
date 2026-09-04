export type PortalMe = {
  account: { id: number; email: string }
  learner: { id: number; first_name: string; full_name: string }
}

export function usePortalAuth() {
  const me = useState<PortalMe | null>('portal-me', () => null)
  const ready = useState<boolean>('portal-ready', () => false)
  const isAuthenticated = computed(() => me.value !== null)

  async function fetchMe(): Promise<PortalMe | null> {
    try {
      me.value = await apiFetch<PortalMe>('/portal/me')
      return me.value
    } catch {
      me.value = null
      return null
    } finally {
      ready.value = true
    }
  }

  async function login(payload: { email: string; password: string }) {
    me.value = await apiFetch<PortalMe>('/portal/login', { method: 'POST', body: payload })
    ready.value = true
    return me.value
  }

  async function activate(payload: { token: string; password: string }) {
    me.value = await apiFetch<PortalMe>('/portal/activate', { method: 'POST', body: payload })
    ready.value = true
    return me.value
  }

  async function peekInvite(token: string) {
    return await apiFetch<{
      state: 'valid' | 'expired' | 'already_connected' | 'invalid'
      learner_first_name: string
      email?: string
      already_activated?: boolean
    }>(
      `/portal/invite?token=${encodeURIComponent(token)}`,
    )
  }

  async function logout() {
    try {
      await apiFetch('/portal/logout', { method: 'POST' })
    } finally {
      me.value = null
      ready.value = true
    }
  }

  return {
    me,
    ready,
    isAuthenticated,
    fetchMe,
    login,
    activate,
    peekInvite,
    logout,
  }
}
