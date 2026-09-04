<script setup lang="ts">
import type { Scene, SceneObject, Stroke, TeachingTool } from '~/utils/teaching/scene'
import { getTemplatePack, drawRoadShapes } from '~/utils/teaching/templates'

const props = withDefaults(
  defineProps<{
    scene: Scene
    objects: SceneObject[]
    strokes: Stroke[]
    tool: TeachingTool
    selectedId: string | null
    penColor?: string
    highlighterColor?: string
    penWidth?: number
    highlighterWidth?: number
  }>(),
  {
    penColor: '#1E2C0F',
    highlighterColor: 'rgba(255, 218, 0, 0.45)',
    penWidth: 3,
    highlighterWidth: 18,
  },
)

const emit = defineEmits<{
  'update:selectedId': [id: string | null]
  'stroke-begin': [tool: Stroke['tool'], color: string, width: number, point: { x: number; y: number; pressure?: number }]
  'stroke-point': [point: { x: number; y: number; pressure?: number }]
  'stroke-end': []
  'erase': [x: number, y: number]
  'object-move': [id: string, x: number, y: number]
  'object-move-end': [id: string, x: number, y: number]
  'object-rotate': [id: string, rotation: number]
  'text-place': [x: number, y: number]
  'pan-zoom': [panX: number, panY: number, zoom: number]
}>()

const LOGICAL = 1000
const HIT_RADIUS = 36

const wrapEl = ref<HTMLElement | null>(null)
const canvasEl = ref<HTMLCanvasElement | null>(null)

const zoom = ref(1)
const panX = ref(0)
const panY = ref(0)

let dpr = 1
let cssW = 0
let cssH = 0
let raf = 0
let dirty = true

let drawing = false
let dragObjId: string | null = null
let dragOffsetX = 0
let dragOffsetY = 0
let rotating = false
let panMode = false
let lastPanX = 0
let lastPanY = 0
let pinchStartDist = 0
let pinchStartZoom = 1

const pointers = new Map<number, { x: number; y: number }>()

watch(
  () => [props.scene, props.objects, props.strokes, props.selectedId, zoom.value, panX.value, panY.value],
  () => {
    dirty = true
  },
  { deep: true },
)

onMounted(() => {
  if (!import.meta.client) return
  resize()
  window.addEventListener('resize', resize)
  const loop = () => {
    if (dirty) {
      paint()
      dirty = false
    }
    raf = requestAnimationFrame(loop)
  }
  raf = requestAnimationFrame(loop)
})

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener('resize', resize)
    cancelAnimationFrame(raf)
  }
})

function resize() {
  if (!wrapEl.value || !canvasEl.value) return
  const rect = wrapEl.value.getBoundingClientRect()
  cssW = rect.width
  cssH = rect.height
  dpr = Math.min(window.devicePixelRatio || 1, 2)
  canvasEl.value.width = Math.max(1, Math.floor(cssW * dpr))
  canvasEl.value.height = Math.max(1, Math.floor(cssH * dpr))
  canvasEl.value.style.width = `${cssW}px`
  canvasEl.value.style.height = `${cssH}px`
  dirty = true
}

function viewScale(): number {
  const base = Math.min(cssW, cssH) / LOGICAL
  return base * zoom.value
}

function screenToLogical(clientX: number, clientY: number): { x: number; y: number } {
  if (!canvasEl.value) return { x: 0, y: 0 }
  const rect = canvasEl.value.getBoundingClientRect()
  const sx = clientX - rect.left
  const sy = clientY - rect.top
  const s = viewScale()
  const ox = (cssW - LOGICAL * s) / 2 + panX.value
  const oy = (cssH - LOGICAL * s) / 2 + panY.value
  return {
    x: (sx - ox) / s,
    y: (sy - oy) / s,
  }
}

function hitTest(lx: number, ly: number): SceneObject | null {
  // Prefer top-most (last) object; large hit targets
  for (let i = props.objects.length - 1; i >= 0; i--) {
    const o = props.objects[i]!
    const r = HIT_RADIUS * (o.scale || 1)
    if (Math.hypot(o.x - lx, o.y - ly) <= r) return o
  }
  return null
}

function paint() {
  const canvas = canvasEl.value
  if (!canvas || cssW < 1) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  ctx.clearRect(0, 0, cssW, cssH)

  // Chalk surface
  ctx.fillStyle = '#F7FAF4'
  ctx.fillRect(0, 0, cssW, cssH)

  const s = viewScale()
  const ox = (cssW - LOGICAL * s) / 2 + panX.value
  const oy = (cssH - LOGICAL * s) / 2 + panY.value

  ctx.save()
  ctx.translate(ox, oy)
  ctx.scale(s, s)

  // Soft board frame
  ctx.fillStyle = '#EEF8E4'
  roundRect(ctx, -8, -8, LOGICAL + 16, LOGICAL + 16, 24)
  ctx.fill()

  const pack = getTemplatePack(props.scene.template || 'blank')
  drawRoadShapes(ctx, pack.shapes, 1, 1)

  for (const stroke of props.strokes) {
    drawStroke(ctx, stroke)
  }

  for (const obj of props.objects) {
    drawObject(ctx, obj, obj.id === props.selectedId)
  }

  ctx.restore()
}

function roundRect(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  w: number,
  h: number,
  r: number,
) {
  ctx.beginPath()
  ctx.moveTo(x + r, y)
  ctx.arcTo(x + w, y, x + w, y + h, r)
  ctx.arcTo(x + w, y + h, x, y + h, r)
  ctx.arcTo(x, y + h, x, y, r)
  ctx.arcTo(x, y, x + w, y, r)
  ctx.closePath()
}

function drawStroke(ctx: CanvasRenderingContext2D, stroke: Stroke) {
  const pts = stroke.points
  if (pts.length < 1) return

  ctx.save()
  if (stroke.tool === 'highlighter') {
    ctx.globalCompositeOperation = 'multiply'
    ctx.strokeStyle = stroke.color
    ctx.lineWidth = stroke.width
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
  } else if (stroke.tool === 'eraser') {
    ctx.restore()
    return
  } else {
    ctx.strokeStyle = stroke.color
    ctx.lineWidth = stroke.width
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
  }

  if (stroke.tool === 'line' || stroke.tool === 'arrow') {
    const a = pts[0]!
    const b = pts[pts.length - 1]!
    ctx.beginPath()
    ctx.moveTo(a.x, a.y)
    ctx.lineTo(b.x, b.y)
    ctx.stroke()
    if (stroke.tool === 'arrow') {
      drawArrowHead(ctx, a.x, a.y, b.x, b.y, stroke.color, stroke.width)
    }
  } else {
    ctx.beginPath()
    ctx.moveTo(pts[0]!.x, pts[0]!.y)
    for (let i = 1; i < pts.length; i++) {
      const p = pts[i]!
      const prev = pts[i - 1]!
      const mx = (prev.x + p.x) / 2
      const my = (prev.y + p.y) / 2
      ctx.quadraticCurveTo(prev.x, prev.y, mx, my)
    }
    const last = pts[pts.length - 1]!
    ctx.lineTo(last.x, last.y)
    ctx.stroke()
  }
  ctx.restore()
}

function drawArrowHead(
  ctx: CanvasRenderingContext2D,
  x1: number,
  y1: number,
  x2: number,
  y2: number,
  color: string,
  width: number,
) {
  const angle = Math.atan2(y2 - y1, x2 - x1)
  const size = 14 + width
  ctx.fillStyle = color
  ctx.beginPath()
  ctx.moveTo(x2, y2)
  ctx.lineTo(x2 - size * Math.cos(angle - 0.4), y2 - size * Math.sin(angle - 0.4))
  ctx.lineTo(x2 - size * Math.cos(angle + 0.4), y2 - size * Math.sin(angle + 0.4))
  ctx.closePath()
  ctx.fill()
}

function drawObject(ctx: CanvasRenderingContext2D, obj: SceneObject, selected: boolean) {
  ctx.save()
  ctx.translate(obj.x, obj.y)
  ctx.rotate((obj.rotation * Math.PI) / 180)
  ctx.scale(obj.scale || 1, obj.scale || 1)

  if (selected) {
    ctx.beginPath()
    ctx.arc(0, 0, HIT_RADIUS + 4, 0, Math.PI * 2)
    ctx.strokeStyle = '#1E2C0F'
    ctx.lineWidth = 3
    ctx.setLineDash([6, 4])
    ctx.stroke()
    ctx.setLineDash([])
  }

  const kind = obj.kind
  if (kind === 'learner_car' || kind === 'other_car' || kind === 'bus') {
    const w = kind === 'bus' ? 44 : 36
    const h = kind === 'bus' ? 70 : 56
    ctx.fillStyle = kind === 'learner_car' ? '#1E2C0F' : kind === 'bus' ? '#ffda00' : '#1E2C0F'
    roundRect(ctx, -w / 2, -h / 2, w, h, 8)
    ctx.fill()
    ctx.fillStyle = kind === 'other_car' ? '#fff' : '#EEF8E4'
    ctx.fillRect(-w / 2 + 6, -h / 2 + 8, w - 12, 14)
  } else if (kind === 'bike') {
    ctx.strokeStyle = '#1E2C0F'
    ctx.lineWidth = 3
    ctx.beginPath()
    ctx.arc(-14, 10, 12, 0, Math.PI * 2)
    ctx.arc(14, 10, 12, 0, Math.PI * 2)
    ctx.stroke()
    ctx.beginPath()
    ctx.moveTo(-14, 10)
    ctx.lineTo(0, -8)
    ctx.lineTo(14, 10)
    ctx.stroke()
  } else if (kind === 'pedestrian') {
    ctx.fillStyle = '#1E2C0F'
    ctx.beginPath()
    ctx.arc(0, -14, 8, 0, Math.PI * 2)
    ctx.fill()
    ctx.fillRect(-7, -4, 14, 28)
  } else if (kind === 'traffic_light') {
    ctx.fillStyle = '#1E2C0F'
    roundRect(ctx, -12, -28, 24, 56, 6)
    ctx.fill()
    const colors = ['#ff4141', '#ffda00', '#C1F48F']
    colors.forEach((c, i) => {
      ctx.fillStyle = c
      ctx.beginPath()
      ctx.arc(0, -16 + i * 16, 6, 0, Math.PI * 2)
      ctx.fill()
    })
  } else if (kind === 'give_way') {
    ctx.fillStyle = '#ff4141'
    ctx.beginPath()
    ctx.moveTo(0, 22)
    ctx.lineTo(-22, -18)
    ctx.lineTo(22, -18)
    ctx.closePath()
    ctx.fill()
    ctx.fillStyle = '#fff'
    ctx.beginPath()
    ctx.moveTo(0, 12)
    ctx.lineTo(-14, -12)
    ctx.lineTo(14, -12)
    ctx.closePath()
    ctx.fill()
  } else if (kind === 'stop') {
    ctx.fillStyle = '#ff4141'
    ctx.beginPath()
    for (let i = 0; i < 8; i++) {
      const a = (i / 8) * Math.PI * 2 - Math.PI / 8
      const r = 24
      const x = Math.cos(a) * r
      const y = Math.sin(a) * r
      if (i === 0) ctx.moveTo(x, y)
      else ctx.lineTo(x, y)
    }
    ctx.closePath()
    ctx.fill()
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 14px Manrope, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText('STOP', 0, 1)
  } else if (kind === 'arrow') {
    ctx.fillStyle = '#1E2C0F'
    ctx.beginPath()
    ctx.moveTo(0, -28)
    ctx.lineTo(16, 8)
    ctx.lineTo(6, 8)
    ctx.lineTo(6, 28)
    ctx.lineTo(-6, 28)
    ctx.lineTo(-6, 8)
    ctx.lineTo(-16, 8)
    ctx.closePath()
    ctx.fill()
  } else if (kind === 'hazard') {
      if (obj.label && obj.isText) {
      ctx.fillStyle = 'rgba(255,255,255,0.92)'
      const text = obj.label
      ctx.font = '20px Manrope, sans-serif'
      const tw = ctx.measureText(text).width
      roundRect(ctx, -tw / 2 - 12, -18, tw + 24, 36, 10)
      ctx.fill()
      ctx.fillStyle = '#1E2C0F'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillText(text, 0, 1)
    } else {
      ctx.fillStyle = '#ffda00'
      ctx.beginPath()
      ctx.moveTo(0, -26)
      ctx.lineTo(24, 22)
      ctx.lineTo(-24, 22)
      ctx.closePath()
      ctx.fill()
      ctx.fillStyle = '#1E2C0F'
      ctx.font = 'bold 22px Manrope, sans-serif'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillText('!', 0, 6)
      if (obj.label) {
        ctx.font = '14px Manrope, sans-serif'
        ctx.fillText(obj.label, 0, 40)
      }
    }
  }

  ctx.restore()
}

function pressureOf(e: PointerEvent): number | undefined {
  if (typeof e.pressure === 'number' && e.pressure > 0) return e.pressure
  return undefined
}

function onPointerDown(e: PointerEvent) {
  if (!canvasEl.value) return
  canvasEl.value.setPointerCapture(e.pointerId)
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })

  if (pointers.size === 2) {
    const pts = [...pointers.values()]
    pinchStartDist = Math.hypot(pts[0]!.x - pts[1]!.x, pts[0]!.y - pts[1]!.y)
    pinchStartZoom = zoom.value
    drawing = false
    dragObjId = null
    return
  }

  const { x, y } = screenToLogical(e.clientX, e.clientY)

  if (props.tool === 'pan' || (e.button === 1) || (e.shiftKey && props.tool === 'select')) {
    panMode = true
    lastPanX = e.clientX
    lastPanY = e.clientY
    return
  }

  if (props.tool === 'select') {
    const hit = hitTest(x, y)
    if (hit) {
      emit('update:selectedId', hit.id)
      dragObjId = hit.id
      dragOffsetX = x - hit.x
      dragOffsetY = y - hit.y
      // Rotate handle: if near edge of selection ring with alt
      rotating = e.altKey
    } else {
      emit('update:selectedId', null)
    }
    return
  }

  if (props.tool === 'text') {
    emit('text-place', x, y)
    return
  }

  if (props.tool === 'eraser') {
    drawing = true
    emit('erase', x, y)
    return
  }

  if (['pen', 'highlighter', 'arrow', 'line'].includes(props.tool)) {
    drawing = true
    const color = props.tool === 'highlighter' ? props.highlighterColor : props.penColor
    const width = props.tool === 'highlighter' ? props.highlighterWidth : props.penWidth
    const pt = { x, y, pressure: pressureOf(e) }
    emit('stroke-begin', props.tool as Stroke['tool'], color, width, pt)
    dirty = true
  }
}

function onPointerMove(e: PointerEvent) {
  if (pointers.has(e.pointerId)) {
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
  }

  if (pointers.size === 2) {
    const pts = [...pointers.values()]
    const dist = Math.hypot(pts[0]!.x - pts[1]!.x, pts[0]!.y - pts[1]!.y)
    if (pinchStartDist > 0) {
      zoom.value = Math.min(3, Math.max(0.4, pinchStartZoom * (dist / pinchStartDist)))
      dirty = true
      emit('pan-zoom', panX.value, panY.value, zoom.value)
    }
    return
  }

  if (panMode) {
    panX.value += e.clientX - lastPanX
    panY.value += e.clientY - lastPanY
    lastPanX = e.clientX
    lastPanY = e.clientY
    dirty = true
    return
  }

  const { x, y } = screenToLogical(e.clientX, e.clientY)

  if (dragObjId) {
    if (rotating) {
      const obj = props.objects.find(o => o.id === dragObjId)
      if (obj) {
        const angle = (Math.atan2(y - obj.y, x - obj.x) * 180) / Math.PI + 90
        emit('object-rotate', dragObjId, angle)
      }
    } else {
      emit('object-move', dragObjId, x - dragOffsetX, y - dragOffsetY)
    }
    dirty = true
    return
  }

  if (!drawing) return

  if (props.tool === 'eraser') {
    emit('erase', x, y)
    dirty = true
    return
  }

  emit('stroke-point', { x, y, pressure: pressureOf(e) })
  dirty = true
}

function onPointerUp(e: PointerEvent) {
  pointers.delete(e.pointerId)
  if (pointers.size < 2) {
    pinchStartDist = 0
  }

  if (panMode) {
    panMode = false
    emit('pan-zoom', panX.value, panY.value, zoom.value)
  }

  if (dragObjId) {
    const obj = props.objects.find(o => o.id === dragObjId)
    if (obj) emit('object-move-end', dragObjId, obj.x, obj.y)
    dragObjId = null
    rotating = false
  }

  if (drawing) {
    drawing = false
    if (props.tool !== 'eraser') emit('stroke-end')
  }
  dirty = true
}

function zoomBy(delta: number) {
  zoom.value = Math.min(3, Math.max(0.4, zoom.value + delta))
  dirty = true
  emit('pan-zoom', panX.value, panY.value, zoom.value)
}

function resetView() {
  zoom.value = 1
  panX.value = 0
  panY.value = 0
  dirty = true
  emit('pan-zoom', 0, 0, 1)
}

defineExpose({ zoomBy, resetView, zoom, panX, panY })
</script>

<template>
  <div ref="wrapEl" class="tcan">
    <canvas
      ref="canvasEl"
      class="tcan__canvas"
      role="img"
      aria-label="Teaching board"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @contextmenu.prevent
    />
    <div class="tcan__zoom" aria-label="Zoom controls">
      <button type="button" class="tcan__zbtn" aria-label="Zoom out" @click="zoomBy(-0.15)">−</button>
      <button type="button" class="tcan__zbtn" aria-label="Reset view" @click="resetView">⊙</button>
      <button type="button" class="tcan__zbtn" aria-label="Zoom in" @click="zoomBy(0.15)">+</button>
    </div>
  </div>
</template>

<style scoped>
.tcan {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 320px;
  background: var(--color-chalk-green);
  border-radius: var(--radius-panel);
  overflow: hidden;
  touch-action: none;
  overscroll-behavior: none;
}

.tcan__canvas {
  display: block;
  width: 100%;
  height: 100%;
  touch-action: none;
  cursor: crosshair;
  -webkit-user-select: none;
  user-select: none;
}

.tcan__zoom {
  position: absolute;
  right: 10px;
  bottom: 10px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.tcan__zbtn {
  width: 44px;
  height: 44px;
  border: none;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.92);
  color: var(--color-ink-black);
  font-size: 20px;
  box-shadow: var(--shadow-soft);
  cursor: pointer;
}

.tcan__zbtn:active {
  transform: scale(0.96);
}

@media (prefers-reduced-motion: reduce) {
  .tcan__zbtn:active {
    transform: none;
  }
}
</style>
