/** Breakpoint helpers for learner portal responsive layouts. */
function createMediaQuery(query: string) {
  const matches = ref(false)

  if (import.meta.client) {
    const mq = window.matchMedia(query)
    matches.value = mq.matches
    const onChange = (e: MediaQueryListEvent) => {
      matches.value = e.matches
    }
    mq.addEventListener('change', onChange)
    onBeforeUnmount(() => mq.removeEventListener('change', onChange))
  }

  return matches
}

export function usePortalLayout() {
  const isDesktop = createMediaQuery('(min-width: 1024px)')
  const isTablet = createMediaQuery('(min-width: 768px)')
  const isWide = createMediaQuery('(min-width: 1440px)')

  return {
    isDesktop,
    isTablet,
    isWide,
  }
}
