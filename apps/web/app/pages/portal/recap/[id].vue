<template>
  <div class="recap">
    <NuxtLink to="/portal" class="recap__back">← {{ t('nav.home') }}</NuxtLink>

    <template v-if="loading && !data">
      <PortalSkeleton variant="line" width="10rem" />
      <PortalSkeleton variant="block" />
    </template>
    <p v-else-if="error" class="recap__error" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <header class="recap__hero">
        <p class="recap__eyebrow">{{ t('recap.title') }}</p>
        <h1 class="recap__title">
          {{ data.is_today ? t('recap.today') : data.date_label }}
        </h1>
        <p class="recap__dur">{{ data.duration_label }}</p>
      </header>

      <section v-if="data.learner_summary" class="recap__block">
        <h2 class="recap__section">{{ t('recap.summary') }}</h2>
        <p class="recap__text">{{ data.learner_summary }}</p>
      </section>

      <section v-if="data.skills.length" class="recap__block">
        <h2 class="recap__section">{{ t('recap.skills') }}</h2>
        <ul class="recap__skills">
          <li v-for="skill in data.skills" :key="skill">{{ skill }}</li>
        </ul>
      </section>

      <section v-if="data.next_focus" class="recap__focus">
        <h2 class="recap__section">{{ t('recap.nextFocus') }}</h2>
        <p class="recap__text">{{ data.next_focus }}</p>
      </section>

      <section v-if="resources.length" class="recap__block">
        <h2 class="recap__section">{{ t('recap.resources') }}</h2>
        <ul class="recap__resources">
          <li v-for="r in resources" :key="r.id">
            <p class="recap__res-title">{{ r.title }}</p>
            <p v-if="r.note" class="recap__res-note">{{ r.note }}</p>
          </li>
        </ul>
      </section>

      <section v-if="places.length" class="recap__block">
        <h2 class="recap__section">{{ t('recap.places') }}</h2>
        <ul class="recap__resources">
          <li v-for="p in places" :key="p.id">
            <p class="recap__res-title">{{ p.label || 'Place to review' }}</p>
            <p v-if="p.note" class="recap__res-note">{{ p.note }}</p>
          </li>
        </ul>
      </section>

      <NuxtLink
        :to="`/portal/lessons/${id}/playback`"
        class="recap__cta"
      >
        {{ t('playback.replayCta') }}
        <span aria-hidden="true">→</span>
      </NuxtLink>

      <NuxtLink
        v-if="data.route_id"
        :to="`/portal/routes/${data.route_id}`"
        class="recap__cta recap__cta--secondary"
      >
        {{ t('recap.viewRoute') }}
        <span aria-hidden="true">→</span>
      </NuxtLink>
    </template>

    <p v-else-if="!loading" class="recap__empty">{{ t('recap.empty') }}</p>
  </div>
</template>

<script setup lang="ts">
import type { PortalRecap } from '~/composables/usePortal'

definePageMeta({ layout: 'portal' })

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { t } = usePortalI18n()
const { fetchRecap } = usePortal()

type LessonResource = {
  id: number
  title: string
  note: string | null
  skill_codes: string[]
  route_moment_id: number | null
}

type Place = {
  id: number
  label: string | null
  note: string | null
}

const data = ref<PortalRecap | null>(null)
const resources = ref<LessonResource[]>([])
const places = ref<Place[]>([])
const loading = ref(true)
const error = ref('')

useHead(() => ({
  title: data.value
    ? `${data.value.date_label} · Recap · OwnLane`
    : 'Lesson recap · OwnLane',
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await fetchRecap(id.value)
    try {
      const packed = await apiFetch<PortalRecap & { resources?: LessonResource[] }>(
        `/portal/lessons/${id.value}/resources`,
      )
      resources.value = packed.resources ?? []
      if (packed.date_label) data.value = packed

      const routeId = packed.route_id ?? data.value?.route_id
      if (routeId) {
        try {
          const routeDetail = await apiFetch<{
            moments?: Array<{ id: number; label: string | null; learner_note: string | null }>
          }>(`/portal/routes/${routeId}`)
          places.value = (routeDetail.moments ?? []).map(m => ({
            id: m.id,
            label: m.label,
            note: m.learner_note,
          }))
        } catch {
          places.value = []
        }
      } else {
        places.value = []
      }
    } catch {
      resources.value = []
      places.value = []
    }
  } catch (e) {
    data.value = null
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
.recap {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-24);
  padding-top: var(--spacing-8);
}

.recap__back {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  text-decoration: none;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.recap__error,
.recap__empty {
  font-size: var(--text-body-sm);
}

.recap__error {
  color: var(--color-danger);
}

.recap__hero {
  margin-left: calc(-1 * var(--spacing-20));
  margin-right: calc(-1 * var(--spacing-20));
  padding: var(--spacing-28) var(--spacing-20);
  background: linear-gradient(
    165deg,
    var(--color-frost-green) 0%,
    var(--color-chalk-green) 100%
  );
}

.recap__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.recap__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-lg);
  line-height: var(--leading-heading-lg);
  letter-spacing: var(--tracking-heading-lg);
  margin-top: var(--spacing-8);
}

.recap__dur {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.recap__section {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-8);
}

.recap__text {
  font-size: var(--text-body);
  line-height: var(--leading-body);
  letter-spacing: var(--tracking-body);
  max-width: 36ch;
}

.recap__skills {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.recap__skills li {
  font-size: var(--text-body-sm);
  padding: 8px 14px;
  background: var(--color-frost-green);
  border-radius: var(--radius-tags);
  min-height: 40px;
  display: inline-flex;
  align-items: center;
}

.recap__resources {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.recap__res-title {
  font-size: var(--text-body-sm);
}

.recap__res-note {
  margin-top: 4px;
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.recap__focus {
  padding: var(--spacing-20) 0;
  border-top: 1px solid var(--color-border);
}

.recap__cta {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  min-height: 48px;
  padding: 10px 18px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  text-decoration: none;
  font-size: var(--text-body-sm);
  box-shadow: var(--shadow-button);
  width: fit-content;
}

.recap__cta--secondary {
  background: transparent;
  color: var(--color-ink-black);
  box-shadow: none;
  border: 1px solid var(--color-border);
}
</style>
