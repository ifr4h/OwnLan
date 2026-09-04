<script setup lang="ts">
definePageMeta({ layout: 'portal' })

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { t } = usePortalI18n()

type PlaybackMoment = {
  id: number
  type: string
  kind: string
  label: string
  learner_note: string | null
  offset_seconds: number | null
  offset_label: string | null
  lat: number
  lng: number
  provenance: string
  explanation: {
    resource_id: number
    title: string
    note: string | null
    scene: Record<string, unknown>
    skill_codes: string[]
  } | null
  related_learn_slugs: string[]
}

type PlaybackChapter = {
  offset_seconds: number | null
  offset_label: string | null
  label: string
  type: string
  moment_id?: number
  resource_id?: number
}

type PlaybackPayload = {
  lesson_id: number
  date_label: string
  duration_minutes: number
  duration_label: string
  learner_summary: string | null
  next_focus: string | null
  skills: Array<{ code: string; label: string; category_label: string }>
  route: {
    id: number
    encoded_polyline: string | null
    duration_seconds: number | null
    distance_metres: number | null
  } | null
  moments: PlaybackMoment[]
  chapters: PlaybackChapter[]
  resources: Array<{
    id: number
    title: string
    note: string | null
    route_moment_id: number | null
    scene: Record<string, unknown>
    provenance: string
  }>
}

const data = ref<PlaybackPayload | null>(null)
const loading = ref(true)
const error = ref('')
const playhead = ref(0)
const focusMomentId = ref<number | null>(null)
const sheetOpen = ref(false)

const selectedMoment = computed(() => {
  if (!data.value || focusMomentId.value == null) return null
  return data.value.moments.find(m => m.id === focusMomentId.value) ?? null
})

const maxOffset = computed(() => {
  if (!data.value) return 1
  const fromRoute = data.value.route?.duration_seconds
  if (fromRoute && fromRoute > 0) return fromRoute
  return Math.max(1, data.value.duration_minutes * 60)
})

const playheadFraction = computed(() => playhead.value / maxOffset.value)

const mapMoments = computed(() =>
  (data.value?.moments ?? []).map(m => ({
    id: m.id,
    lat: m.lat,
    lng: m.lng,
    label: m.label,
    offset_seconds: m.offset_seconds,
  })),
)

useHead(() => ({
  title: data.value
    ? `${data.value.date_label} · Replay · OwnLane`
    : `${t('playback.title')} · OwnLane`,
}))

function selectMoment(momentId: number) {
  focusMomentId.value = momentId
  const m = data.value?.moments.find(x => x.id === momentId)
  if (m?.offset_seconds != null) {
    playhead.value = m.offset_seconds
  }
  sheetOpen.value = true
}

function onChapter(chapter: PlaybackChapter) {
  if (chapter.moment_id) {
    selectMoment(chapter.moment_id)
    return
  }
  if (chapter.offset_seconds != null) {
    playhead.value = chapter.offset_seconds
    focusMomentId.value = null
    sheetOpen.value = false
  }
}

function onScrub(e: Event) {
  const el = e.target as HTMLInputElement
  playhead.value = Number(el.value)
  // Snap focus to nearest moment within 20s if close
  const moments = data.value?.moments ?? []
  let nearest: PlaybackMoment | null = null
  let best = Infinity
  for (const m of moments) {
    if (m.offset_seconds == null) continue
    const d = Math.abs(m.offset_seconds - playhead.value)
    if (d < best) {
      best = d
      nearest = m
    }
  }
  if (nearest && best <= 20) {
    focusMomentId.value = nearest.id
  }
}

function closeSheet() {
  sheetOpen.value = false
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await apiFetch<PlaybackPayload>(`/portal/lessons/${id.value}/playback`)
    playhead.value = 0
    focusMomentId.value = data.value.moments[0]?.id ?? null
    sheetOpen.value = !!focusMomentId.value
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

<template>
  <div class="playback">
    <NuxtLink :to="`/portal/recap/${id}`" class="playback__back">
      ← {{ t('playback.backToRecap') }}
    </NuxtLink>

    <p v-if="loading" class="playback__msg">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="playback__err" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <header class="playback__hero">
        <p class="playback__eyebrow">{{ t('playback.title') }}</p>
        <h1 class="playback__title">{{ data.date_label }}</h1>
        <p class="playback__meta">{{ data.duration_label }}</p>
        <p v-if="data.learner_summary" class="playback__summary">{{ data.learner_summary }}</p>
      </header>

      <ClientOnly>
        <PortalPlaybackMap
          v-if="data.route?.encoded_polyline"
          :encoded-polyline="data.route.encoded_polyline"
          :moments="mapMoments"
          :focus-moment-id="focusMomentId"
          :playhead-fraction="playheadFraction"
          :aria-label="t('playback.mapLabel')"
          @select-moment="selectMoment"
        />
        <p v-else class="playback__msg">{{ t('playback.noRoute') }}</p>
        <template #fallback>
          <div class="playback__map-fallback" aria-hidden="true" />
        </template>
      </ClientOnly>

      <section class="playback__timeline" :aria-label="t('playback.timeline')">
        <label class="playback__scrub-label" for="playback-scrub">
          {{ t('playback.timeline') }}
        </label>
        <input
          id="playback-scrub"
          class="playback__scrub"
          type="range"
          min="0"
          :max="maxOffset"
          step="1"
          :value="playhead"
          @input="onScrub"
        >
        <div class="playback__chips" role="list">
          <button
            v-for="(ch, i) in data.chapters"
            :key="i"
            type="button"
            class="playback__chip"
            :class="{
              'playback__chip--active':
                ch.moment_id != null && ch.moment_id === focusMomentId,
            }"
            role="listitem"
            @click="onChapter(ch)"
          >
            <span v-if="ch.offset_label" class="playback__chip-time">{{ ch.offset_label }}</span>
            {{ ch.label }}
          </button>
        </div>
      </section>

      <section v-if="data.skills.length" class="playback__skills">
        <h2 class="playback__h">{{ t('playback.skills') }}</h2>
        <p class="playback__prov">{{ t('playback.skillsProvenance') }}</p>
        <ul class="playback__skill-list">
          <li v-for="s in data.skills" :key="s.code">{{ s.label }}</li>
        </ul>
      </section>

      <section v-if="data.chapters.length" class="playback__chapters">
        <h2 class="playback__h">{{ t('playback.chapters') }}</h2>
        <ol class="playback__chapter-list">
          <li v-for="(ch, i) in data.chapters" :key="i">
            <button type="button" class="playback__chapter-btn" @click="onChapter(ch)">
              <span v-if="ch.offset_label" class="playback__chapter-time">{{ ch.offset_label }}</span>
              {{ ch.label }}
            </button>
          </li>
        </ol>
      </section>

      <!-- Moment panel / bottom sheet -->
      <div
        v-if="sheetOpen && selectedMoment"
        class="playback__sheet"
        role="dialog"
        aria-modal="false"
        :aria-label="selectedMoment.label"
      >
        <button
          type="button"
          class="playback__sheet-close"
          :aria-label="t('common.back')"
          @click="closeSheet"
        >
          ×
        </button>
        <p class="playback__sheet-eyebrow">
          {{ selectedMoment.offset_label || t('playback.moment') }}
        </p>
        <h2 class="playback__sheet-title">{{ selectedMoment.label }}</h2>
        <p v-if="selectedMoment.learner_note" class="playback__sheet-note">
          {{ selectedMoment.learner_note }}
        </p>
        <template v-if="selectedMoment.explanation">
          <p v-if="selectedMoment.explanation.note" class="playback__sheet-note">
            {{ selectedMoment.explanation.note }}
          </p>
          <LearnSceneViewer
            v-if="selectedMoment.explanation.scene && Object.keys(selectedMoment.explanation.scene).length"
            :scene="selectedMoment.explanation.scene as any"
            :aria-label="selectedMoment.explanation.title"
          />
        </template>
        <div
          v-if="selectedMoment.related_learn_slugs?.length"
          class="playback__related"
        >
          <p class="playback__h">{{ t('playback.relatedLearn') }}</p>
          <NuxtLink
            v-for="slug in selectedMoment.related_learn_slugs"
            :key="slug"
            :to="`/portal/learn/${slug}`"
            class="playback__related-link"
          >
            {{ slug.replace(/^interactive-/, '').replace(/-/g, ' ') }}
          </NuxtLink>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.playback {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
  padding-top: var(--spacing-8);
  position: relative;
}

.playback__back {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--color-muted);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.playback__hero {
  padding: var(--spacing-24);
  background: linear-gradient(165deg, var(--color-frost-green), var(--color-chalk-green));
  border-radius: var(--radius-panel);
}

@media (min-width: 1024px) {
  .playback {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.8fr);
    gap: var(--spacing-32);
    align-items: start;
  }

  .playback__back,
  .playback__msg,
  .playback__err,
  .playback__hero {
    grid-column: 1 / -1;
  }

  .playback__map-fallback {
    min-height: 480px;
    height: min(62vh, 560px);
  }

  .playback :deep(.playback-map),
  .playback :deep(.map-wrap) {
    min-height: 480px;
  }

  .playback__timeline,
  .playback__skills,
  .playback__chapters {
    grid-column: 1;
  }

  .playback__sheet {
    position: static;
    margin: 0;
    grid-column: 2;
    grid-row: 2 / span 6;
    align-self: start;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-panel);
    padding: var(--spacing-20);
    background: var(--surface-card);
    box-shadow: none;
  }
}

.playback__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin: 0;
}

.playback__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-lg);
  letter-spacing: var(--tracking-heading-lg);
  margin: var(--spacing-8) 0 0;
}

.playback__meta {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.playback__summary {
  margin-top: var(--spacing-12);
  font-size: var(--text-body-sm);
  max-width: 40ch;
}

.playback__map-fallback {
  height: min(52vh, 420px);
  min-height: 260px;
  background: var(--color-frost-green);
}

.playback__timeline {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.playback__scrub-label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.playback__scrub {
  width: 100%;
  accent-color: var(--color-ownlane-green);
  min-height: 44px;
}

.playback__chips {
  display: flex;
  gap: var(--spacing-8);
  overflow-x: auto;
  padding-bottom: 4px;
  -webkit-overflow-scrolling: touch;
}

.playback__chip {
  flex-shrink: 0;
  min-height: 40px;
  padding: 8px 14px;
  border: none;
  border-radius: var(--radius-tags);
  background: var(--color-frost-green);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
  white-space: nowrap;
}

.playback__chip--active {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.playback__chip-time {
  font-family: var(--font-martian-mono);
  margin-right: 6px;
  opacity: 0.75;
}

.playback__h {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin: 0 0 var(--spacing-8);
}

.playback__prov {
  font-size: var(--text-meta);
  color: var(--color-muted);
  margin: 0 0 var(--spacing-8);
}

.playback__skill-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.playback__skill-list li {
  padding: 8px 14px;
  background: var(--color-frost-green);
  border-radius: var(--radius-tags);
  font-size: var(--text-body-sm);
  min-height: 40px;
  display: inline-flex;
  align-items: center;
}

.playback__chapter-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}

.playback__chapter-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-12);
  width: 100%;
  min-height: 48px;
  padding: 10px 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
  color: inherit;
}

.playback__chapter-time {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  color: var(--color-ownlane-green);
  min-width: 3.5rem;
}

.playback__sheet {
  position: sticky;
  bottom: calc(72px + env(safe-area-inset-bottom));
  margin-left: calc(-1 * var(--spacing-20));
  margin-right: calc(-1 * var(--spacing-20));
  padding: var(--spacing-20);
  padding-bottom: calc(var(--spacing-20) + env(safe-area-inset-bottom));
  background: color-mix(in srgb, var(--color-frost-green) 88%, var(--color-paper-white));
  border-radius: 24px 24px 0 0;
  box-shadow: 0 -8px 32px rgba(17, 17, 24, 0.08);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  z-index: 2;
}

.playback__sheet-close {
  position: absolute;
  top: 12px;
  right: 16px;
  min-width: 44px;
  min-height: 44px;
  border: none;
  background: transparent;
  font-size: 24px;
  cursor: pointer;
  color: var(--color-muted);
}

.playback__sheet-eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin: 0;
}

.playback__sheet-title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  margin: 0;
  padding-right: 40px;
}

.playback__sheet-note {
  margin: 0;
  font-size: var(--text-body-sm);
  max-width: 42ch;
}

.playback__related {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.playback__related-link {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--color-ownlane-green);
  text-decoration: none;
  font-size: var(--text-body-sm);
  text-transform: capitalize;
}

.playback__msg {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.playback__err {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

@media (prefers-reduced-motion: reduce) {
  .playback__sheet {
    transition: none;
  }
}
</style>
