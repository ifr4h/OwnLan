export async function apiFetch<T>(
  path: string,
  options: Parameters<typeof $fetch<T>>[1] = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(options?.headers as Record<string, string> | undefined ?? {}),
  }

  // Nuxt SSR $fetch to /api does not automatically forward the browser cookie.
  // Without this, auth middleware thinks every refresh is logged out.
  if (import.meta.server) {
    const reqHeaders = useRequestHeaders(['cookie'])
    if (reqHeaders.cookie) {
      headers.cookie = reqHeaders.cookie
    }
  }

  return await $fetch<T>(`/api${path}`, {
    ...options,
    credentials: 'include',
    headers,
  })
}

export function extractApiError(e: unknown, fallback = 'Something went wrong.'): string {
  const err = e as { data?: { message?: string }; statusMessage?: string }
  return err?.data?.message || err?.statusMessage || fallback
}

/** Same-origin path only — blocks open redirects. */
export function safeAppRedirect(path: unknown): string | null {
  if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) {
    return null
  }
  return path
}

export function useApi() {
  return {
    apiFetch,
    extractApiError,
    safeAppRedirect,
  }
}
