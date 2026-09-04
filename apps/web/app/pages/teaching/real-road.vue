<script setup lang="ts">
import { createEmptyScene, createStroke, type Stroke } from '~/utils/teaching/scene'

const { t } = useTeachingI18n()
const { createResource } = useTeaching()

const mode = ref<'move' | 'draw'>('move')
const title = ref('')
const saving = ref(false)
const error = ref('')
const saveMsg = ref('')

const mapEl = ref<HTMLElement | null>(null)
const overlayEl = ref<HTMLCanvasElement | null>(null)

const center = ref({ lat: 51.5074, lng: -0.1278 })
const zoom = ref(17)
const strokes = ref<Stroke[]>([])
const liveId = ref<string | null>(null)

let map: import('leaflet').Map | null = null
let drawing = false

useHead({ title: 'Real junction · Teaching · OwnLane' })

onMounted(async () => {
  if (!import.meta.client || !mapEl.value) return
  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        center.value = { lat: pos.coords.latitude, lng: pos.coords.longitude }
        map?.setView([center.value.lat, center.value.lng], zoom.value)
      },
      () => {},
      { enableHighAccuracy: true, timeout: 8000 },
    )
  }

  map = L.map(mapEl.value, {
    zoomControl: true,
    attributionControl: true,
  }).setView([center.value.lat, center.value.lng], zoom.value)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map)

  map.on('moveend', () => {
    const c = map!.getCenter()
    center.value = { lat: c.lat, lng: c.lng }
    zoom.value = map!.getZoom()
  })

  resizeOverlay()
  window.addEventListener('resize', resizeOverlay)
  paintOverlay()
})

onBeforeUnmount(() => {
  if (import.meta.client) window.removeEventListener('resize', resizeOverlay)
  map?.remove()
  map = null
})

watch(mode, (m) => {
  if (!map) return
  if (m === 'draw') {
    map.dragging.disable()
    map.scrollWheelZoom.disable()
    map.touchZoom.disable()
  } else {
    map.dragging.enable()
    map.scrollWheelZoom.enable()
    map.touchZoom.enable()
  }
})

function resizeOverlay() {
  if (!overlayEl.value || !mapEl.value) return
  const rect = mapEl.value.getBoundingClientRect()
  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  overlayEl.value.width = Math.floor(rect.width * dpr)
  overlayEl.value.height = Math.floor(rect.height * dpr)
  overlayEl.value.style.width = `${rect.width}px`
  overlayEl.value.style.height = `${rect.height}px`
  paintOverlay()
}

function paintOverlay() {
  const canvas = overlayEl.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return
  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  const w = canvas.width / dpr
  const h = canvas.height / dpr
  ctx.clearRect(0, 0, w, h)

  for (const stroke of strokes.value) {
    const pts = stroke.points
    if (pts.length < 2) continue
    ctx.beginPath()
    ctx.strokeStyle = stroke.color
    ctx.lineWidth = stroke.width
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.moveTo(pts[0]!.x, pts[0]!.y)
    for (let i = 1; i < pts.length; i++) {
      ctx.lineTo(pts[i]!.x, pts[i]!.y)
    }
    ctx.stroke()
  }
}

function localPoint(e: PointerEvent) {
  if (!overlayEl.value) return { x: 0, y: 0 }
  const rect = overlayEl.value.getBoundingClientRect()
  return { x: e.clientX - rect.left, y: e.clientY - rect.top }
}

function onPointerDown(e: PointerEvent) {
  if (mode.value !== 'draw' || !overlayEl.value) return
  overlayEl.value.setPointerCapture(e.pointerId)
  drawing = true
  const p = localPoint(e)
  const stroke = createStroke('pen', '#111118', 4, [p])
  strokes.value.push(stroke)
  liveId.value = stroke.id
  paintOverlay()
}

function onPointerMove(e: PointerEvent) {
  if (!drawing || !liveId.value) return
  const stroke = strokes.value.find(s => s.id === liveId.value)
  if (!stroke) return
  stroke.points.push(localPoint(e))
  paintOverlay()
}

function onPointerUp() {
  drawing = false
  liveId.value = null
}

function clearDrawings() {
  strokes.value = []
  paintOverlay()
}

async function onSave() {
  saving.value = true
  error.value = ''
  saveMsg.value = ''
  try {
    const name = title.value.trim() || 'Real junction'
    const scene = createEmptyScene('blank')
    scene.template = 'real_road'
    scene.map = {
      center_lat: center.value.lat,
      center_lng: center.value.lng,
      zoom: zoom.value,
      overlay_strokes: strokes.value,
    }
    scene.strokes = strokes.value
    const created = await createResource({
      title: name,
      kind: 'real_road',
      template_code: 'real_road',
      scene,
    })
    saveMsg.value = t('board.saved')
    await navigateTo(`/teaching/${created.id}`, { replace: true })
  } catch (e) {
    error.value = extractApiError(e, 'Could not save.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="real ol-page">
    <NuxtLink to="/teaching" class="ol-back">← Teaching</NuxtLink>
    <header class="real__head">
      <h1 class="ol-page-title">{{ t('realRoad.title') }}</h1>
      <p class="ol-meta">{{ t('realRoad.hint') }}</p>
    </header>

    <div class="real__modes" role="group" aria-label="Map mode">
      <button
        type="button"
        class="real__mode"
        :class="{ 'real__mode--on': mode === 'move' }"
        :aria-pressed="mode === 'move'"
        @click="mode = 'move'"
      >
        {{ t('realRoad.move') }}
      </button>
      <button
        type="button"
        class="real__mode"
        :class="{ 'real__mode--on': mode === 'draw' }"
        :aria-pressed="mode === 'draw'"
        @click="mode = 'draw'"
      >
        {{ t('realRoad.draw') }}
      </button>
      <button type="button" class="real__mode real__mode--ghost" @click="clearDrawings">
        {{ t('board.clear') }}
      </button>
    </div>

    <div class="real__map-wrap">
      <div ref="mapEl" class="real__map" />
      <canvas
        ref="overlayEl"
        class="real__overlay"
        :class="{ 'real__overlay--draw': mode === 'draw' }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
      />
    </div>

    <div class="real__save">
      <input
        v-model="title"
        class="real__title"
        type="text"
        :placeholder="t('board.titlePlaceholder')"
      >
      <button
        type="button"
        class="ol-btn"
        :disabled="saving"
        @click="onSave"
      >
        {{ saving ? t('board.saving') : t('realRoad.save') }}
      </button>
    </div>
    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-if="saveMsg" class="ol-meta">{{ saveMsg }}</p>
  </section>
</template>

<style scoped>
.real {
  gap: var(--spacing-16);
  max-width: var(--content-wide-max);
}

.real__modes {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.real__mode {
  min-height: 44px;
  padding: 8px 16px;
  border: none;
  border-radius: 14px;
  background: var(--color-frost-green);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
}

.real__mode--on {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.real__mode--ghost {
  background: transparent;
  border: 1px solid var(--color-border);
}

.real__map-wrap {
  position: relative;
  height: min(55vh, 520px);
  min-height: 280px;
  border-radius: var(--radius-panel);
  overflow: hidden;
  background: var(--color-frost-green);
}

.real__map {
  width: 100%;
  height: 100%;
  z-index: 0;
}

.real__overlay {
  position: absolute;
  inset: 0;
  z-index: 400;
  pointer-events: none;
  touch-action: none;
}

.real__overlay--draw {
  pointer-events: auto;
  cursor: crosshair;
}

.real__save {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.real__title {
  flex: 1;
  min-width: 160px;
  min-height: 48px;
  padding: 10px 14px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--surface-canvas);
  font: inherit;
}
</style>
