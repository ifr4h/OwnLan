<template>
  <div class="route">
    <NuxtLink to="/portal/routes" class="route__back">← {{ t('nav.routes') }}</NuxtLink>

    <template v-if="loading && !detail">
      <PortalSkeleton variant="hero" />
      <PortalSkeleton variant="line" />
    </template>
    <p v-else-if="error" class="route__error" role="alert">{{ error }}</p>

    <template v-else-if="detail">
      <header class="route__head">
        <h1 class="route__title">{{ detail.date_label }}</h1>
        <p class="route__meta">
          <span v-if="detail.distance_label">{{ detail.distance_label }}</span>
          <span v-if="detail.distance_label && detail.duration_label"> · </span>
          <span v-if="detail.duration_label">{{ detail.duration_label }}</span>
        </p>
      </header>

      <div class="route__map">
        <ClientOnly>
          <PortalRouteMap
            v-if="detail.encoded_polyline"
            :encoded-polyline="detail.encoded_polyline"
            :distance-label="detail.distance_label"
            :duration-label="detail.duration_label"
          />
          <p v-else class="route__no-map">{{ t('routes.noGeometry') }}</p>
        </ClientOnly>
      </div>

      <section v-if="detail.next_focus" class="route__block">
        <h2 class="route__section">{{ t('routes.focus') }}</h2>
        <p class="route__text">{{ detail.next_focus }}</p>
      </section>

      <section v-if="detail.learner_summary" class="route__block">
        <h2 class="route__section">{{ t('routes.summary') }}</h2>
        <p class="route__text">{{ detail.learner_summary }}</p>
      </section>

      <section v-if="detail.skills.length" class="route__block">
        <h2 class="route__section">{{ t('recap.skills') }}</h2>
        <ul class="route__skills">
          <li v-for="skill in detail.skills" :key="skill">{{ skill }}</li>
        </ul>
      </section>

      <section v-if="detail.moments?.length" class="route__block">
        <h2 class="route__section">{{ t('routes.moments') }}</h2>
        <ul class="route__moments">
          <li v-for="m in detail.moments" :key="m.id" class="route__moment">
            <span class="route__moment-offset">{{ m.offset_label || '—' }}</span>
            <div>
              <p class="route__moment-label">{{ m.label || m.kind }}</p>
              <p v-if="m.learner_note" class="route__moment-note">{{ m.learner_note }}</p>
            </div>
          </li>
        </ul>
      </section>

      <NuxtLink
        v-if="detail.lesson_id"
        :to="`/portal/recap/${detail.lesson_id}`"
        class="route__link"
      >
        {{ t('home.viewRecap') }}
        <span aria-hidden="true">→</span>
      </NuxtLink>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PortalRouteDetail } from '~/composables/usePortal'

type PortalMoment = {
  id: number
  offset_seconds: number | null
  offset_label: string | null
  lat: number
  lng: number
  kind: string
  label: string | null
  learner_note: string | null
}

type RouteWithMoments = PortalRouteDetail & { moments?: PortalMoment[] }

definePageMeta({ layout: 'portal' })

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { t } = usePortalI18n()
const { fetchRoute } = usePortal()

const detail = ref<RouteWithMoments | null>(null)
const loading = ref(true)
const error = ref('')

useHead(() => ({
  title: detail.value
    ? `${detail.value.date_label} · Route · OwnLane`
    : 'Route · OwnLane',
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    detail.value = await fetchRoute(id.value) as RouteWithMoments
  } catch (e) {
    detail.value = null
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})

watch(id, () => {
  void load()
})
</script>

<style scoped>
.route {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
  padding-top: var(--spacing-8);
}

.route__back {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  text-decoration: none;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.route__error {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.route__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
  letter-spacing: var(--tracking-heading-md);
}

.route__meta {
  margin-top: var(--spacing-4);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.route__map {
  margin-left: calc(-1 * var(--spacing-20));
  margin-right: calc(-1 * var(--spacing-20));
}

@media (min-width: 1024px) {
  .route__map {
    margin: 0;
    min-height: 480px;
    border-radius: var(--radius-panel);
    overflow: hidden;
  }
}

.route__no-map {
  padding: var(--spacing-40) var(--spacing-20);
  background: var(--color-frost-green);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  text-align: center;
}

.route__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-8);
}

.route__text {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
}

.route__skills {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.route__skills li {
  font-size: var(--text-meta);
  padding: 6px 12px;
  background: var(--color-frost-green);
  border-radius: var(--radius-tags);
  min-height: 32px;
  display: inline-flex;
  align-items: center;
}

.route__moments {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.route__moment {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  min-height: 48px;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border);
}

.route__moment-offset {
  font-family: var(--font-martian-mono);
  font-size: var(--text-meta);
  min-width: 3rem;
  opacity: 0.7;
}

.route__moment-label {
  font-size: var(--text-body-sm);
}

.route__moment-note {
  margin-top: 4px;
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.route__link {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 44px;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
}
</style>
