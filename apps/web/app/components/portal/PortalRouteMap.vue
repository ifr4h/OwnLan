<template>
  <div class="map-wrap">
    <div
      ref="mapEl"
      class="map"
      role="img"
      :aria-label="ariaLabel"
    />
    <p class="map__alt">
      <span v-if="distanceLabel">{{ distanceLabel }}</span>
      <span v-if="distanceLabel && durationLabel"> · </span>
      <span v-if="durationLabel">{{ durationLabel }}</span>
      <span v-if="!distanceLabel && !durationLabel">{{ emptyAlt }}</span>
    </p>
  </div>
</template>

<script setup lang="ts">
import { decodePolyline } from '~/utils/polyline'
import { prefersReducedMotion } from '~/utils/portalFormat'

const props = withDefaults(
  defineProps<{
    encodedPolyline?: string | null
    distanceLabel?: string | null
    durationLabel?: string | null
    ariaLabel?: string
    emptyAlt?: string
  }>(),
  {
    encodedPolyline: null,
    distanceLabel: null,
    durationLabel: null,
    ariaLabel: 'Lesson route map',
    emptyAlt: 'No map available',
  },
)

const mapEl = ref<HTMLElement | null>(null)
let map: import('leaflet').Map | null = null
let lineLayer: import('leaflet').Polyline | null = null

onMounted(async () => {
  if (!import.meta.client || !mapEl.value) return
  const L = await import('leaflet')
  await import('leaflet/dist/leaflet.css')

  const points = props.encodedPolyline
    ? decodePolyline(props.encodedPolyline)
    : []

  map = L.map(mapEl.value, {
    zoomControl: false,
    attributionControl: true,
    dragging: points.length > 0,
    scrollWheelZoom: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 18,
  }).addTo(map)

  L.control.zoom({ position: 'bottomright' }).addTo(map)

  if (points.length >= 2) {
    const latlngs = points.map(([lat, lng]) => L.latLng(lat, lng))
    lineLayer = L.polyline([], {
      color: '#168B55',
      weight: 4,
      opacity: 0.92,
      lineJoin: 'round',
      lineCap: 'round',
    }).addTo(map)

    map.fitBounds(L.latLngBounds(latlngs), { padding: [28, 28] })

    if (prefersReducedMotion()) {
      lineLayer.setLatLngs(latlngs)
    } else {
      await animateDraw(lineLayer, latlngs)
    }
  } else {
    map.setView([51.5074, -0.1278], 11)
  }
})

async function animateDraw(
  layer: import('leaflet').Polyline,
  latlngs: import('leaflet').LatLng[],
) {
  const step = Math.max(1, Math.floor(latlngs.length / 40))
  for (let i = 2; i <= latlngs.length; i += step) {
    layer.setLatLngs(latlngs.slice(0, i))
    await new Promise(r => setTimeout(r, 16))
  }
  layer.setLatLngs(latlngs)
}

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }
})

watch(
  () => props.encodedPolyline,
  async (encoded) => {
    if (!map || !import.meta.client) return
    const L = await import('leaflet')
    if (lineLayer) {
      map.removeLayer(lineLayer)
      lineLayer = null
    }
    const points = encoded ? decodePolyline(encoded) : []
    if (points.length < 2) return
    const latlngs = points.map(([lat, lng]) => L.latLng(lat, lng))
    lineLayer = L.polyline(latlngs, {
      color: '#168B55',
      weight: 4,
      opacity: 0.92,
    }).addTo(map)
    map.fitBounds(L.latLngBounds(latlngs), { padding: [28, 28] })
  },
)
</script>

<style scoped>
.map-wrap {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.map {
  width: 100%;
  height: 240px;
  border-radius: 0;
  background: var(--color-frost-green);
  z-index: 0;
}

.map__alt {
  font-size: var(--text-meta);
  color: var(--color-muted);
}
</style>
