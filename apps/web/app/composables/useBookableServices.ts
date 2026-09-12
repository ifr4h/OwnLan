import type { CatalogueService } from '~/composables/useServices'

/**
 * Active bookable services for diary booking chips.
 */
export function useBookableServices() {
  const { fetchHome } = useServices()
  const services = ref<CatalogueService[]>([])
  const loading = ref(false)
  const loaded = ref(false)

  async function ensureLoaded() {
    if (loaded.value || loading.value) return
    loading.value = true
    try {
      const home = await fetchHome()
      services.value = home.services.filter(s => s.status === 'active')
      loaded.value = true
    } catch {
      services.value = []
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  const defaultService = computed(() =>
    services.value.find(s => s.is_default) || services.value[0] || null,
  )

  return {
    services,
    loading,
    loaded,
    defaultService,
    ensureLoaded,
  }
}
