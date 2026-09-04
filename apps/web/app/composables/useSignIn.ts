import type { MeResponse } from '~/composables/useAuth'
import type { PortalMe } from '~/composables/usePortalAuth'

export type SignInAccountType = 'instructor' | 'learner'

export type SignInResponse = {
  account_type: SignInAccountType
  redirect: string
  payload: MeResponse | PortalMe
}

export function useSignIn() {
  async function signIn(payload: { email: string; password: string }): Promise<SignInResponse> {
    const result = await apiFetch<SignInResponse>('/auth/sign-in', {
      method: 'POST',
      body: payload,
    })

    if (result.account_type === 'instructor') {
      const auth = useAuth()
      auth.me.value = result.payload as MeResponse
      auth.ready.value = true
      usePortalAuth().me.value = null
      usePortalAuth().ready.value = true
    } else {
      const portal = usePortalAuth()
      portal.me.value = result.payload as PortalMe
      portal.ready.value = true
      useAuth().me.value = null
      useAuth().ready.value = true
    }

    return result
  }

  return { signIn }
}
