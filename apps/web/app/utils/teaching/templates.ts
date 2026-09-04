/**
 * Geometry packs for Teaching Studio road boards.
 * Coordinates are in a 0–1000 logical canvas space (centre ~500,500).
 */

export type RoadShape =
  | { type: 'rect'; x: number; y: number; w: number; h: number; fill?: string }
  | { type: 'circle'; cx: number; cy: number; r: number; fill?: string; stroke?: string; strokeWidth?: number }
  | {
      type: 'line'
      x1: number
      y1: number
      x2: number
      y2: number
      stroke?: string
      strokeWidth?: number
      dash?: number[]
    }
  | {
      type: 'arc'
      cx: number
      cy: number
      r: number
      startAngle: number
      endAngle: number
      stroke?: string
      strokeWidth?: number
      fill?: string
    }
  | { type: 'poly'; points: Array<[number, number]>; fill?: string; stroke?: string; strokeWidth?: number }

export type TemplatePack = {
  code: string
  label: string
  category: string
  shapes: RoadShape[]
}

const ASPHALT = '#3a3f44'
const ASPHALT_LIGHT = '#4a5056'
const MARKING = '#f5f5f0'
const ISLAND = '#EEF8E4'
const GRASS = '#A8D5B5'

function horizontalRoad(y: number, thickness = 90): RoadShape[] {
  return [
    { type: 'rect', x: 0, y: y - thickness / 2, w: 1000, h: thickness, fill: ASPHALT },
    {
      type: 'line',
      x1: 40,
      y1: y,
      x2: 960,
      y2: y,
      stroke: MARKING,
      strokeWidth: 3,
      dash: [24, 18],
    },
  ]
}

function verticalRoad(x: number, thickness = 90): RoadShape[] {
  return [
    { type: 'rect', x: x - thickness / 2, y: 0, w: thickness, h: 1000, fill: ASPHALT },
    {
      type: 'line',
      x1: x,
      y1: 40,
      x2: x,
      y2: 960,
      stroke: MARKING,
      strokeWidth: 3,
      dash: [24, 18],
    },
  ]
}

function roundaboutRing(cx: number, cy: number, outerR: number, innerR: number, arms = 4): RoadShape[] {
  const shapes: RoadShape[] = [
    { type: 'circle', cx, cy, r: outerR + 28, fill: ASPHALT },
    { type: 'circle', cx, cy, r: innerR, fill: ISLAND },
    {
      type: 'circle',
      cx,
      cy,
      r: (outerR + innerR) / 2,
      fill: 'transparent',
      stroke: MARKING,
      strokeWidth: 3,
    },
  ]
  const armLen = 220
  const armW = 80
  for (let i = 0; i < arms; i++) {
    const angle = (i / arms) * Math.PI * 2 - Math.PI / 2
    const cos = Math.cos(angle)
    const sin = Math.sin(angle)
    const midR = outerR + armLen / 2
    const mx = cx + cos * midR
    const my = cy + sin * midR
    // Approximate arm as rotated rect via poly
    const hw = armW / 2
    const hl = armLen / 2
    const perpX = -sin
    const perpY = cos
    shapes.push({
      type: 'poly',
      points: [
        [mx + cos * hl + perpX * hw, my + sin * hl + perpY * hw],
        [mx + cos * hl - perpX * hw, my + sin * hl - perpY * hw],
        [mx - cos * hl - perpX * hw, my - sin * hl - perpY * hw],
        [mx - cos * hl + perpX * hw, my - sin * hl + perpY * hw],
      ],
      fill: ASPHALT,
    })
  }
  return shapes
}

const PACKS: Record<string, () => RoadShape[]> = {
  blank: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...horizontalRoad(500, 100),
  ],

  t_junction: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...horizontalRoad(620, 95),
    { type: 'rect', x: 455, y: 80, w: 90, h: 540, fill: ASPHALT },
    {
      type: 'line',
      x1: 500,
      y1: 100,
      x2: 500,
      y2: 560,
      stroke: MARKING,
      strokeWidth: 3,
      dash: [20, 16],
    },
    // Give-way marking on stem
    {
      type: 'line',
      x1: 470,
      y1: 560,
      x2: 530,
      y2: 560,
      stroke: MARKING,
      strokeWidth: 5,
    },
  ],

  crossroads: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...horizontalRoad(500, 95),
    ...verticalRoad(500, 95),
    // Junction box outline
    {
      type: 'rect',
      x: 430,
      y: 430,
      w: 140,
      h: 140,
      fill: ASPHALT_LIGHT,
    },
  ],

  mini_roundabout: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...horizontalRoad(500, 85),
    ...verticalRoad(500, 85),
    { type: 'circle', cx: 500, cy: 500, r: 55, fill: ASPHALT },
    { type: 'circle', cx: 500, cy: 500, r: 22, fill: MARKING },
  ],

  roundabout: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...roundaboutRing(500, 500, 160, 70, 4),
  ],

  multi_roundabout: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...roundaboutRing(500, 500, 210, 80, 4),
    // Outer lane marking
    {
      type: 'circle',
      cx: 500,
      cy: 500,
      r: 155,
      fill: 'transparent',
      stroke: MARKING,
      strokeWidth: 2,
    },
    {
      type: 'circle',
      cx: 500,
      cy: 500,
      r: 185,
      fill: 'transparent',
      stroke: MARKING,
      strokeWidth: 2,
    },
  ],

  traffic_lights: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    ...horizontalRoad(500, 100),
    ...verticalRoad(500, 100),
    // Stop lines
    { type: 'line', x1: 400, y1: 430, x2: 470, y2: 430, stroke: MARKING, strokeWidth: 6 },
    { type: 'line', x1: 530, y1: 570, x2: 600, y2: 570, stroke: MARKING, strokeWidth: 6 },
    { type: 'line', x1: 430, y1: 530, x2: 430, y2: 600, stroke: MARKING, strokeWidth: 6 },
    { type: 'line', x1: 570, y1: 400, x2: 570, y2: 470, stroke: MARKING, strokeWidth: 6 },
    // Light posts as small rects
    { type: 'rect', x: 410, y: 400, w: 18, h: 36, fill: '#1E2C0F' },
    { type: 'rect', x: 572, y: 564, w: 18, h: 36, fill: '#1E2C0F' },
    { type: 'rect', x: 400, y: 572, w: 36, h: 18, fill: '#1E2C0F' },
    { type: 'rect', x: 564, y: 410, w: 36, h: 18, fill: '#1E2C0F' },
  ],

  dual_carriageway: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    { type: 'rect', x: 0, y: 280, w: 1000, h: 140, fill: ASPHALT },
    { type: 'rect', x: 0, y: 580, w: 1000, h: 140, fill: ASPHALT },
    // Central reservation
    { type: 'rect', x: 0, y: 430, w: 1000, h: 140, fill: ISLAND },
    // Lane markings
    {
      type: 'line',
      x1: 40,
      y1: 350,
      x2: 960,
      y2: 350,
      stroke: MARKING,
      strokeWidth: 3,
      dash: [28, 20],
    },
    {
      type: 'line',
      x1: 40,
      y1: 650,
      x2: 960,
      y2: 650,
      stroke: MARKING,
      strokeWidth: 3,
      dash: [28, 20],
    },
    // Direction chevrons hint
    { type: 'line', x1: 200, y1: 320, x2: 240, y2: 350, stroke: MARKING, strokeWidth: 4 },
    { type: 'line', x1: 200, y1: 380, x2: 240, y2: 350, stroke: MARKING, strokeWidth: 4 },
  ],

  parking: () => [
    { type: 'rect', x: 0, y: 0, w: 1000, h: 1000, fill: GRASS },
    { type: 'rect', x: 80, y: 200, w: 840, h: 600, fill: ASPHALT_LIGHT },
    // Access road
    { type: 'rect', x: 420, y: 800, w: 160, h: 200, fill: ASPHALT },
    // Bays
    ...Array.from({ length: 6 }, (_, i) => {
      const x = 120 + i * 130
      return {
        type: 'rect' as const,
        x,
        y: 240,
        w: 100,
        h: 180,
        fill: ASPHALT,
      }
    }),
    ...Array.from({ length: 6 }, (_, i) => {
      const x = 120 + i * 130
      return {
        type: 'line' as const,
        x1: x,
        y1: 240,
        x2: x,
        y2: 420,
        stroke: MARKING,
        strokeWidth: 2,
      }
    }),
    {
      type: 'line',
      x1: 120,
      y1: 420,
      x2: 880,
      y2: 420,
      stroke: MARKING,
      strokeWidth: 2,
    },
  ],
}

export const TEMPLATE_META: Array<{ code: string; label: string; category: string }> = [
  { code: 'blank', label: 'Blank road', category: 'basics' },
  { code: 't_junction', label: 'T-junction', category: 'junctions' },
  { code: 'crossroads', label: 'Crossroads', category: 'junctions' },
  { code: 'mini_roundabout', label: 'Mini-roundabout', category: 'roundabouts' },
  { code: 'roundabout', label: 'Standard roundabout', category: 'roundabouts' },
  { code: 'multi_roundabout', label: 'Multi-lane roundabout', category: 'roundabouts' },
  { code: 'traffic_lights', label: 'Traffic-light junction', category: 'junctions' },
  { code: 'dual_carriageway', label: 'Dual carriageway', category: 'roads' },
  { code: 'parking', label: 'Parking area', category: 'manoeuvres' },
]

export function getTemplatePack(code: string): TemplatePack {
  const meta = TEMPLATE_META.find(t => t.code === code) ?? TEMPLATE_META[0]!
  const factory = PACKS[code] ?? PACKS.blank!
  return {
    code: meta.code,
    label: meta.label,
    category: meta.category,
    shapes: factory(),
  }
}

export function drawRoadShapes(
  ctx: CanvasRenderingContext2D,
  shapes: RoadShape[],
  scaleX: number,
  scaleY: number,
): void {
  for (const shape of shapes) {
    ctx.save()
    if (shape.type === 'rect') {
      ctx.fillStyle = shape.fill ?? ASPHALT
      ctx.fillRect(shape.x * scaleX, shape.y * scaleY, shape.w * scaleX, shape.h * scaleY)
    } else if (shape.type === 'circle') {
      ctx.beginPath()
      ctx.ellipse(
        shape.cx * scaleX,
        shape.cy * scaleY,
        shape.r * scaleX,
        shape.r * scaleY,
        0,
        0,
        Math.PI * 2,
      )
      if (shape.fill && shape.fill !== 'transparent') {
        ctx.fillStyle = shape.fill
        ctx.fill()
      }
      if (shape.stroke) {
        ctx.strokeStyle = shape.stroke
        ctx.lineWidth = (shape.strokeWidth ?? 2) * Math.min(scaleX, scaleY)
        ctx.stroke()
      }
    } else if (shape.type === 'line') {
      ctx.beginPath()
      ctx.moveTo(shape.x1 * scaleX, shape.y1 * scaleY)
      ctx.lineTo(shape.x2 * scaleX, shape.y2 * scaleY)
      ctx.strokeStyle = shape.stroke ?? MARKING
      ctx.lineWidth = (shape.strokeWidth ?? 2) * Math.min(scaleX, scaleY)
      if (shape.dash) {
        ctx.setLineDash(shape.dash.map(d => d * Math.min(scaleX, scaleY)))
      }
      ctx.stroke()
      ctx.setLineDash([])
    } else if (shape.type === 'arc') {
      ctx.beginPath()
      ctx.ellipse(
        shape.cx * scaleX,
        shape.cy * scaleY,
        shape.r * scaleX,
        shape.r * scaleY,
        0,
        shape.startAngle,
        shape.endAngle,
      )
      if (shape.fill) {
        ctx.fillStyle = shape.fill
        ctx.fill()
      }
      ctx.strokeStyle = shape.stroke ?? MARKING
      ctx.lineWidth = (shape.strokeWidth ?? 3) * Math.min(scaleX, scaleY)
      ctx.stroke()
    } else if (shape.type === 'poly') {
      const pts = shape.points
      if (pts.length < 2) continue
      ctx.beginPath()
      ctx.moveTo(pts[0]![0] * scaleX, pts[0]![1] * scaleY)
      for (let i = 1; i < pts.length; i++) {
        ctx.lineTo(pts[i]![0] * scaleX, pts[i]![1] * scaleY)
      }
      ctx.closePath()
      if (shape.fill) {
        ctx.fillStyle = shape.fill
        ctx.fill()
      }
      if (shape.stroke) {
        ctx.strokeStyle = shape.stroke
        ctx.lineWidth = (shape.strokeWidth ?? 2) * Math.min(scaleX, scaleY)
        ctx.stroke()
      }
    }
    ctx.restore()
  }
}
