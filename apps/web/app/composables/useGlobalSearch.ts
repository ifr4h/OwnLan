export type SearchAction = {
  label: string
  path: string
}

export type SearchItem = {
  id: number
  type: string
  title: string
  meta: string | null
  path: string
  actions?: SearchAction[]
}

export type SearchGroup = {
  type: string
  label: string
  items: SearchItem[]
}

export type QuickAction = {
  id: string
  label: string
  path: string
}

export type SearchResult = {
  query: string
  groups: SearchGroup[]
  quick_actions: QuickAction[]
}

export type RecentSearch = {
  pupils: SearchItem[]
  quick_actions: QuickAction[]
}

export function useGlobalSearch() {
  async function search(query: string): Promise<SearchResult> {
    const q = encodeURIComponent(query)
    return await apiFetch<SearchResult>(`/search?q=${q}`)
  }

  async function fetchRecent(): Promise<RecentSearch> {
    return await apiFetch<RecentSearch>('/search/recent')
  }

  return { search, fetchRecent }
}
