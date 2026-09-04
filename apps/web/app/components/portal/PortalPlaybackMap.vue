<template>
  <div class="playback-map">
    <div
      ref="mapEl"
      class="playback-map__el"
      role="img"
      :aria-label="ariaLabel"
    />
  </div>
</template>

<script setup lang="ts">
import { decodePolyline } from '~/utils/polyline'

export type PlaybackMoment = {
  id: number
  lat: number
  lng: number
  label: string
  offset_seconds?: number | null
}

const props = withDefaults(
  defineProps<{
    encodedPolyline?: string | null
    moments?: PlaybackMoment[]
    focusMomentId?: number | null
    playheadFraction?: number
    ariaLabel?: string
  }>(),
  {
    encodedPolyline: null,
    moments: () => [],
    focusMomentId: null,
    playheadFraction: 0,
    ariaLabel: 'Lesson playback map',
  },
)

const emit = defineEmits<{
  'select-moment': [id: number]
}>()

const mapEl = ref<HTMLElement | null>(null)
let map: import('leaflet').Map | null = null
let lineLayer: import('leaflet').Polyline | null = null
let carMarker: import('leaflet').Marker | null = null
const momentMarkers = new Map<number, import('leaflet').Marker>()
let latlngs: import('leaflet').LatLng[] = []
let Lref: typeof import('leaflet') | null = null

function interpolateAlong(fraction: number): import('leaflet').LatLng | null {
  if (!Lref || latlngs.length < 2) return null
  const t = Math.min(1, Math.max(0, fraction))
  if (t <= 0) return latlngs[0]!
  if (t >= 1) return latlngs[latlngs.length - 1]!

  let total = 0
  const segs: number[] = []
  for (let i = 1; i < latlngs.length; i++) {
    const d = latlngs[i - 1]!.distanceTo(latlngs[i]!)
    segs.push(d)
    total += d
  }
  if (total <= 0) return latlngs[0]!
  let remain = total * t
  for (let i = 0; i < segs.length; i++) {
    const d = segs[i]!
    if (remain <= d) {
      const r = d === 0 ? 0 : remain / d
      const a = latlngs[i]!
      const b = latlngs[i + 1]!
      return Lref.latLng(
        a.lat + (b.lat - a.lat) * r,
        a.lng + (b.lng - a.lng) * r,
      )
    }
    remain -= d
  }
  return latlngs[latlngs.length - 1]!
}

function carIcon(L: typeof import('leaflet')) {
  return L.divIcon({
    className: 'playback-car',
    html: '<span class="playback-car__dot"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 11],
  })
}

function momentIcon(L: typeof import('leaflet'), focused: boolean) {
  return L.divIcon({
    className: focused ? 'playback-pin playback-pin--focus' : 'playback-pin',
    html: '<span class="playback-pin__dot"></span>',
    iconSize: [28, 28],
    iconAnchor: [14, 14],
  })
}

function updateCar() {
  if (!map || !Lref || !carMarker) return
  const pos = interpolateAlong(props.playheadFraction ?? 0)
  if (pos) carMarker.setLatLng(pos)
}

function syncMoments() {
  if (!map || !Lref) return
  for (const [, m] of momentMarkers) {
    map.removeLayer(m)
  }
  momentMarkers.clear()
  for (const moment of props.moments) {
    const focused = moment.id === props.focusMomentId
    const marker = Lref.marker([moment.lat, moment.lng], {
      icon: momentIcon(Lref, focused),
      title: moment.label,
      riseOnHover: true,
    })
    marker.on('click', () => emit('select-moment', moment.id))
    marker.addTo(map)
    momentMarkers.set(moment.id, marker)
  }
}

onMounted(async () => {
  if (!import.meta.client || !mapEl.value) return
  const L = await import('leaflet')
  Lref = L
  await import('leaflet/dist/leaflet.css')

  const points = props.encodedPolyline ? decodePolyline(props.encodedPolyline) : []
  map = L.map(mapEl.value, {
    zoomControl: false,
    attributionControl: true,
    scrollWheelZoom: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 18,
  }).addTo(map)

  L.control.zoom({ position: 'bottomright' }).addTo(map)

  if (points.length >= 2) {
    latlngs = points.map(([lat, lng]) => L.latLng(lat, lng))
    lineLayer = L.polyline(latlngs, {
      color: '#C1F48F',
      weight: 5,
      opacity: 0.9,
      lineJoin: 'round',
      lineCap: 'round',
    }).addTo(map)
    map.fitBounds(L.latLngBounds(latlngs), { padding: [36, 36] })
    carMarker = L.marker(latlngs[0]!, { icon: carIcon(L), interactive: false }).addTo(map)
    updateCar()
  } else {
    map.setView([51.5074, -0.1278], 11)
  }

  syncMoments()
})

watch(
  () => props.playheadFraction,
  () => updateCar(),
)

watch(
  () => [props.moments, props.focusMomentId] as const,
  () => syncMoments(),
  { deep: true },
)

watch(
  () => props.encodedPolyline,
  async (encoded) => {
    if (!map || !Lref || !import.meta.client) return
    if (lineLayer) {
      map.removeLayer(lineLayer)
      lineLayer = null
    }
    if (carMarker) {
      map.removeLayer(carMarker)
      carMarker = null
    }
    const points = encoded ? decodePolyline(encoded) : []
    if (points.length < 2) {
      latlngs = []
      return
    }
    latlngs = points.map(([lat, lng]) => Lref!.latLng(lat, lng))
    lineLayer = Lref.polyline(latlngs, {
      color: '#C1F48F',
      weight: 5,
      opacity: 0.9,
    }).addTo(map)
    map.fitBounds(Lref.latLngBounds(latlngs), { padding: [36, 36] })
    carMarker = Lref.marker(latlngs[0]!, { icon: carIcon(Lref), interactive: false }).addTo(map)
    updateCar()
  },
)

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<style scoped>
.playback-map {
  width: 100%;
}

.playback-map__el {
  width: 100%;
  height: min(52vh, 420px);
  min-height: 260px;
  background: var(--color-frost-green);
  z-index: 0;
}

.playback-map :deep(.playback-car__dot) {
  display: block;
  width: 18px;
  height: 18px;
  border-radius: 999px;
  background: var(--color-ownlane-green);
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(17, 17, 24, 0.28);
}

.playback-map :deep(.playback-pin__dot) {
  display: block;
  width: 14px;
  height: 14px;
  margin: 7px;
  border-radius: 999px;
  background: var(--color-ink-black);
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(17, 17, 24, 0.25);
  cursor: pointer;
}

.playback-map :deep(.playback-pin--focus .playback-pin__dot) {
  width: 18px;
  height: 18px;
  margin: 5px;
  background: var(--color-ownlane-green);
}
</style>
