<script setup lang="ts">
import { getTemplatePack, drawRoadShapes } from '~/utils/teaching/templates'
import type { Scene, SceneObject, Stroke } from '~/utils/teaching/scene'
import { sceneAtStep } from '~/utils/teaching/scene'

const props = withDefaults(
  defineProps<{
    scene: Partial<Scene> & { template?: string; objects?: SceneObject[]; strokes?: Stroke[] }
    stepIndex?: number
    ariaLabel?: string
  }>(),
  {
    stepIndex: 0,
    ariaLabel: 'Lesson explanation scene',
  },
)

const LOGICAL = 1000
const canvasEl = ref<HTMLCanvasElement | null>(null)

const view = computed(() => {
  const base: Scene = {
    template: props.scene.template || 'blank',
    version: props.scene.version ?? 1,
    objects: props.scene.objects ?? [],
    strokes: props.scene.strokes ?? [],
    steps: props.scene.steps ?? [],
    active_step: props.scene.active_step ?? 0,
    map: props.scene.map ?? null,
  }
  if (base.steps.length) {
    return sceneAtStep(base, props.stepIndex)
  }
  return { objects: base.objects, strokes: base.strokes }
})

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

function drawStroke(ctx: CanvasRenderingContext2D, stroke: Stroke) {
  const pts = stroke.points
  if (pts.length < 1) return
  ctx.save()
  if (stroke.tool === 'highlighter') {
    ctx.globalCompositeOperation = 'multiply'
  }
  if (stroke.tool === 'eraser') {
    ctx.restore()
    return
  }
  ctx.strokeStyle = stroke.color
  ctx.lineWidth = stroke.width
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
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
      ctx.quadraticCurveTo(prev.x, prev.y, (prev.x + p.x) / 2, (prev.y + p.y) / 2)
    }
    const last = pts[pts.length - 1]!
    ctx.lineTo(last.x, last.y)
    ctx.stroke()
  }
  ctx.restore()
}

function drawObject(ctx: CanvasRenderingContext2D, obj: SceneObject) {
  ctx.save()
  ctx.translate(obj.x, obj.y)
  ctx.rotate((obj.rotation * Math.PI) / 180)
  ctx.scale(obj.scale || 1, obj.scale || 1)
  const kind = obj.kind
  if (kind === 'learner_car' || kind === 'other_car' || kind === 'bus') {
    const w = kind === 'bus' ? 44 : 36
    const h = kind === 'bus' ? 70 : 56
    ctx.fillStyle = kind === 'learner_car' ? '#111118' : kind === 'bus' ? '#ffda00' : '#111118'
    roundRect(ctx, -w / 2, -h / 2, w, h, 8)
    ctx.fill()
    ctx.fillStyle = kind === 'other_car' ? '#fff' : '#E2F0E7'
    ctx.fillRect(-w / 2 + 6, -h / 2 + 8, w - 12, 14)
  } else if (kind === 'bike') {
    ctx.strokeStyle = '#111118'
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
    ctx.fillStyle = '#111118'
    ctx.beginPath()
    ctx.arc(0, -14, 8, 0, Math.PI * 2)
    ctx.fill()
    ctx.fillRect(-7, -4, 14, 28)
  } else if (kind === 'traffic_light') {
    ctx.fillStyle = '#111118'
    roundRect(ctx, -12, -28, 24, 56, 6)
    ctx.fill()
    ;['#ff4141', '#ffda00', '#16ab59'].forEach((c, i) => {
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
  } else if (kind === 'stop') {
    ctx.fillStyle = '#ff4141'
    ctx.beginPath()
    for (let i = 0; i < 8; i++) {
      const a = (i / 8) * Math.PI * 2 - Math.PI / 8
      const r = 24
      if (i === 0) ctx.moveTo(Math.cos(a) * r, Math.sin(a) * r)
      else ctx.lineTo(Math.cos(a) * r, Math.sin(a) * r)
    }
    ctx.closePath()
    ctx.fill()
    ctx.fillStyle = '#fff'
    ctx.font = 'bold 14px Manrope, sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText('STOP', 0, 1)
  } else if (kind === 'arrow') {
    ctx.fillStyle = '#111118'
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
      ctx.font = '20px Manrope, sans-serif'
      const tw = ctx.measureText(obj.label).width
      roundRect(ctx, -tw / 2 - 12, -18, tw + 24, 36, 10)
      ctx.fill()
      ctx.fillStyle = '#111118'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillText(obj.label, 0, 1)
    } else {
      ctx.fillStyle = '#ffda00'
      ctx.beginPath()
      ctx.moveTo(0, -26)
      ctx.lineTo(24, 22)
      ctx.lineTo(-24, 22)
      ctx.closePath()
      ctx.fill()
      ctx.fillStyle = '#111118'
      ctx.font = 'bold 22px Manrope, sans-serif'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillText('!', 0, 6)
    }
  }
  ctx.restore()
}

function paint() {
  const canvas = canvasEl.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const dpr = typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1
  const cssW = canvas.clientWidth || 320
  const cssH = canvas.clientHeight || 240
  canvas.width = Math.round(cssW * dpr)
  canvas.height = Math.round(cssH * dpr)
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)

  const scale = Math.min(cssW, cssH) / LOGICAL
  const ox = (cssW - LOGICAL * scale) / 2
  const oy = (cssH - LOGICAL * scale) / 2

  ctx.clearRect(0, 0, cssW, cssH)
  ctx.fillStyle = '#E2F0E7'
  ctx.fillRect(0, 0, cssW, cssH)

  ctx.save()
  ctx.translate(ox, oy)
  ctx.scale(scale, scale)

  const pack = getTemplatePack(props.scene.template || 'blank')
  drawRoadShapes(ctx, pack.shapes, 1, 1)

  for (const stroke of view.value.strokes) {
    drawStroke(ctx, stroke)
  }
  for (const obj of view.value.objects) {
    drawObject(ctx, obj)
  }

  ctx.restore()
}

watch(
  () => [props.scene, props.stepIndex] as const,
  () => nextTick(paint),
  { deep: true },
)

onMounted(() => {
  paint()
  if (typeof window !== 'undefined') window.addEventListener('resize', paint)
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') window.removeEventListener('resize', paint)
})
</script>

<template>
  <div class="viewer">
    <canvas ref="canvasEl" class="viewer__canvas" role="img" :aria-label="ariaLabel" />
  </div>
</template>

<style scoped>
.viewer {
  width: 100%;
  border-radius: var(--radius-small);
  overflow: hidden;
  background: var(--color-frost-green);
}

.viewer__canvas {
  display: block;
  width: 100%;
  height: min(48vw, 300px);
  min-height: 180px;
}
</style>
