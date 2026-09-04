export type AccountantExportSummary = {
  from: string
  to: string
  range_label: string
  includes: string[]
}

export function useDataExport() {
  function downloadUrl(type: string, from?: string, to?: string): string {
    const query = new URLSearchParams({ type })
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    return `/api/exports/download?${query.toString()}`
  }

  function accountantPackUrl(from?: string, to?: string): string {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    const suffix = query.toString() ? `?${query.toString()}` : ''
    return `/api/exports/accountant-pack${suffix}`
  }

  async function fetchAccountantSummary(from?: string, to?: string): Promise<AccountantExportSummary> {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    const suffix = query.toString() ? `?${query.toString()}` : ''
    return await apiFetch<AccountantExportSummary>(`/exports/accountant-summary${suffix}`)
  }

  return {
    downloadUrl,
    accountantPackUrl,
    fetchAccountantSummary,
  }
}
