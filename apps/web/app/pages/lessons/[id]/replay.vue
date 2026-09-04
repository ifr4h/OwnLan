<script setup lang="ts">
import type { RouteMoment } from '~/composables/useTeaching'

const route = useRoute()
const lessonId = computed(() => Number(route.params.id))
const { t } = useTeachingI18n()
const { fetchMoments, updateMoment } = useTeaching()

const moments = ref<RouteMoment[]>([])
const selectedId = ref<number | null>(null)
const loading = ref(true)
const error = ref('')
const savingId = ref<number | null>(null)

const selected = computed(() => moments.value.find(m => m.id === selectedId.value) ?? null)

const mapEl = ref<HTMLElement | null>(null)
let map: import('leaflet').Map | null = null
let markers: import('leaflet').CircleMarker[] = []
let Lmod: typeof import('leaflet') | null = null

useHead({ title: 'Drive replay · OwnLane' })

onMounted(async () => {
  await load()
  await initMap()
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    moments.value = await fetchMoments(lessonId.value)
    if (moments.value[0]) selectedId.value = moments.value[0].id
  } catch (e) {
    error.value = extractApiError(e, 'Could not load replay.')
  } finally {
    loading.value = false
  }
}

async function initMap() {
  if (!import.meta.client || !mapEl.value) return
  Lmod = await import('leaflet')
  await import('leaflet/dist/leaflet.css')
  const L = Lmod

  map = L.map(mapEl.value, {
    zoomControl: true,
    scrollWheelZoom: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 18,
  }).addTo(map)

  if (moments.value.length) {
    const bounds = L.latLngBounds(moments.value.map(m => L.latLng(m.lat, m.lng)))
    map.fitBounds(bounds.pad(0.25))
  } else {
    map.setView([51.5074, -0.1278], 11)
  }

  redrawMarkers()
}

function redrawMarkers() {
  if (!map || !Lmod) return
  for (const m of markers) m.remove()
  markers = []
  for (const moment of moments.value) {
    const marker = Lmod.circleMarker([moment.lat, moment.lng], {
      radius: moment.id === selectedId.value ? 10 : 7,
      color: '#111118',
      fillColor: moment.id === selectedId.value ? '#111118' : '#E2F0E7',
      fillOpacity: 1,
      weight: 2,
    }).addTo(map)
    marker.on('click', () => {
      selectedId.value = moment.id
    })
    markers.push(marker)
  }
}

watch(selectedId, () => {
  redrawMarkers()
  const m = moments.value.find(x => x.id === selectedId.value)
  if (m && map) map.panTo([m.lat, m.lng])
})

async function saveSelected() {
  if (!selected.value) return
  savingId.value = selected.value.id
  try {
    const updated = await updateMoment(selected.value.id, {
      kind: selected.value.kind,
      label: selected.value.label,
      learner_note: selected.value.learner_note,
      learner_visible: selected.value.learner_visible,
    })
    moments.value = moments.value.map(m => (m.id === updated.id ? updated : m))
  } catch (e) {
    error.value = extractApiError(e, 'Could not save moment.')
  } finally {
    savingId.value = null
  }
}

function explainHref(m: RouteMoment) {
  return `/teaching/board?lessonId=${lessonId.value}&momentId=${m.id}&lat=${m.lat}&lng=${m.lng}&template=blank`
}

function kindLabel(kind: string) {
  const key = `moments.kinds.${kind}`
  const label = t(key)
  return label === key ? kind : label
}
</script>

<template>
  <section class="replay ol-page">
    <NuxtLink :to="`/lessons/${lessonId}`" class="ol-back">← Lesson</NuxtLink>
    <header>
      <p class="ol-eyebrow">{{ t('replay.title') }}</p>
      <h1 class="ol-page-title">{{ t('replay.timeline') }}</h1>
    </header>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>

    <template v-else>
      <div class="replay__map-wrap">
        <div ref="mapEl" class="replay__map" />
      </div>

      <ul v-if="moments.length" class="replay__list">
        <li
          v-for="m in moments"
          :key="m.id"
          class="replay__row"
          :class="{ 'replay__row--on': m.id === selectedId }"
        >
          <button type="button" class="replay__pick" @click="selectedId = m.id">
            <span class="replay__offset">{{ m.offset_label || '—' }}</span>
            <span>{{ m.label || kindLabel(m.kind) }}</span>
          </button>
        </li>
      </ul>
      <p v-else class="ol-muted">{{ t('moments.empty') }}</p>

      <form
        v-if="selected"
        class="replay__form"
        @submit.prevent="saveSelected"
      >
        <h2 class="replay__form-title">{{ t('moments.enrich') }}</h2>
        <label class="field">
          <span class="field__label">{{ t('moments.kind') }}</span>
          <select v-model="selected.kind" class="field__input">
            <option value="review">{{ t('moments.kinds.review') }}</option>
            <option value="good">{{ t('moments.kinds.good') }}</option>
            <option value="explain">{{ t('moments.kinds.explain') }}</option>
            <option value="hazard">{{ t('moments.kinds.hazard') }}</option>
            <option value="roundabout">{{ t('moments.kinds.roundabout') }}</option>
            <option value="custom">{{ t('moments.kinds.custom') }}</option>
          </select>
        </label>
        <label class="field">
          <span class="field__label">{{ t('moments.label') }}</span>
          <input v-model="selected.label" class="field__input" type="text">
        </label>
        <label class="field">
          <span class="field__label">{{ t('moments.learnerNote') }}</span>
          <textarea v-model="selected.learner_note" class="field__input" rows="3" />
        </label>
        <label class="check">
          <input v-model="selected.learner_visible" type="checkbox">
          {{ t('moments.learnerVisible') }}
        </label>
        <div class="replay__actions">
          <button class="ol-btn" type="submit" :disabled="savingId === selected.id">
            {{ savingId === selected.id ? 'Saving…' : 'Save note' }}
          </button>
          <NuxtLink class="ol-btn ol-btn--ghost" :to="explainHref(selected)">
            {{ t('moments.explain') }}
          </NuxtLink>
        </div>
      </form>
    </template>
  </section>
</template>

<style scoped>
.replay {
  gap: var(--spacing-16);
  max-width: var(--content-wide-max);
}

.replay__map-wrap {
  height: min(40vh, 360px);
  min-height: 220px;
  border-radius: var(--radius-panel);
  overflow: hidden;
  background: var(--color-frost-green);
}

.replay__map {
  width: 100%;
  height: 100%;
}

.replay__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.replay__row--on .replay__pick {
  background: var(--color-success-wash);
  color: var(--color-ownlane-green);
}

.replay__pick {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 48px;
  padding: 10px 12px;
  border: none;
  border-radius: 12px;
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
}

.replay__offset {
  font-family: var(--font-martian-mono);
  font-size: var(--text-meta);
  min-width: 3rem;
  opacity: 0.7;
}

.replay__form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding: var(--spacing-16);
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
}

.replay__form-title {
  font-size: var(--text-body-sm);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field__label {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.field__input {
  min-height: 44px;
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-small);
  background: var(--color-paper-white);
  font: inherit;
}

.check {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  font-size: var(--text-body-sm);
}

.replay__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
