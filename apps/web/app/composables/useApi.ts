export async function apiFetch<T>(
  path: string,
  options: Parameters<typeof $fetch<T>>[1] = {},
): Promise<T> {
  return await $fetch<T>(`/api${path}`, {
    ...options,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(options?.headers ?? {}),
    },
  })
}

export function extractApiError(e: unknown, fallback = 'Something went wrong.'): string {
  const err = e as { data?: { message?: string }; statusMessage?: string }
  return err?.data?.message || err?.statusMessage || fallback
}
