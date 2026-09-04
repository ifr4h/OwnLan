<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">{{ t('routes.title') }}</h1>
    </template>

    <template v-if="loading && !items.length">
      <PortalSkeleton variant="hero" />
      <PortalSkeleton variant="line" />
    </template>
    <p v-else-if="error" class="routes__error" role="alert">{{ error }}</p>

    <template v-else>
      <p v-if="!items.length" class="routes__empty">{{ t('routes.empty') }}</p>

      <PortalMainGrid v-else variant="master-detail">
        <div class="routes__list-panel">
          <ul class="routes__list">
            <li v-for="item in items" :key="item.id">
              <button
                type="button"
                class="routes__row"
                :class="{ 'routes__row--active': selectedId === item.id }"
                @click="selectRoute(item.id)"
              >
                <div>
                  <p class="routes__date">{{ item.date_label }}</p>
                  <p class="routes__meta">
                    <span v-if="item.distance_label">{{ item.distance_label }}</span>
                    <span v-if="item.distance_label && item.duration_label"> · </span>
                    <span v-if="item.duration_label">{{ item.duration_label }}</span>
                  </p>
                  <p v-if="item.skills.length" class="routes__skills">
                    {{ item.skills.slice(0, 3).join(' · ') }}
                  </p>
                </div>
                <span class="routes__chevron portal-hide-mobile" aria-hidden="true">→</span>
              </button>
            </li>
          </ul>
        </div>

        <div class="routes__map-panel">
          <template v-if="selectedDetail">
            <div v-if="selectedDetail.encoded_polyline" class="routes__map">
              <ClientOnly>
                <PortalRouteMap
                  :encoded-polyline="selectedDetail.encoded_polyline"
                  :distance-label="selectedDetail.distance_label"
                  :duration-label="selectedDetail.duration_label"
                />
              </ClientOnly>
            </div>
            <p v-else class="routes__no-map">{{ t('routes.noGeometry') }}</p>
            <NuxtLink
              v-if="selectedId"
              :to="`/portal/routes/${selectedId}`"
              class="routes__detail-link"
            >
              {{ t('routes.viewMap') }}
              <span aria-hidden="true">→</span>
            </NuxtLink>
          </template>
          <p v-else class="routes__pick portal-hide-mobile">{{ t('routes.selectHint') }}</p>
        </div>
      </PortalMainGrid>
    </template>
  </PortalPage>
</template>

<script setup lang="ts">
import type { PortalRouteDetail, PortalRouteSummary } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })
useHead({ title: 'Routes · OwnLane' })

const { t } = usePortalI18n()
const { fetchRoutes, fetchRoute } = usePortal()

const items = ref<PortalRouteSummary[]>([])
const selectedId = ref<number | null>(null)
const selectedDetail = ref<PortalRouteDetail | null>(null)
const loading = ref(true)
const error = ref('')

async function selectRoute(id: number) {
  selectedId.value = id
  try {
    selectedDetail.value = await fetchRoute(id)
  } catch {
    selectedDetail.value = null
  }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await fetchRoutes()
    items.value = res.items
    if (res.items[0]) {
      await selectRoute(res.items[0].id)
    }
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.routes__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.routes__empty,
.routes__pick,
.routes__no-map {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.routes__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.routes__row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  min-height: 72px;
  padding: var(--spacing-12) 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
  color: inherit;
  text-decoration: none;
}

.routes__row--active {
  color: var(--color-ownlane-green);
}

.routes__date {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.routes__meta,
.routes__skills {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin-top: 2px;
}

.routes__chevron {
  color: var(--color-muted);
}

.routes__map-panel {
  min-width: 0;
}

.routes__map {
  min-height: 280px;
  border-radius: var(--radius-panel);
  overflow: hidden;
}

.routes__map :deep(.map-wrap) {
  min-height: inherit;
}

.routes__map :deep(.map) {
  min-height: 280px;
}

@media (min-width: 1024px) {
  .routes__map,
  .routes__map :deep(.map) {
    min-height: 420px;
  }
}

.routes__detail-link {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  margin-top: var(--spacing-16);
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

@media (max-width: 1023px) {
  .routes__map-panel {
    margin-top: var(--spacing-24);
    padding-top: var(--spacing-24);
    border-top: 1px solid var(--color-border);
  }
}
</style>
