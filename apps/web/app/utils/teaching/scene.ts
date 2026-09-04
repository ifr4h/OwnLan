/** Teaching Studio scene model — board annotations, objects, and steps. */

export type TeachingTool =
  | 'select'
  | 'pen'
  | 'highlighter'
  | 'arrow'
  | 'line'
  | 'eraser'
  | 'text'
  | 'pan'

export type SceneObjectKind =
  | 'learner_car'
  | 'other_car'
  | 'bus'
  | 'bike'
  | 'pedestrian'
  | 'traffic_light'
  | 'give_way'
  | 'stop'
  | 'arrow'
  | 'hazard'

export type SceneObject = {
  id: string
  kind: SceneObjectKind
  x: number
  y: number
  rotation: number
  scale: number
  label?: string
  /** Free-text annotation rendered as a label chip */
  isText?: boolean
}

export type StrokePoint = {
  x: number
  y: number
  pressure?: number
}

export type Stroke = {
  id: string
  tool: 'pen' | 'highlighter' | 'arrow' | 'line' | 'eraser'
  color: string
  width: number
  points: StrokePoint[]
  /** For arrow/line: first and last point define the segment. */
}

export type SceneStep = {
  id: string
  label: string
  objects: SceneObject[]
  strokes: Stroke[]
  note: string
}

export type SceneMap = {
  center_lat: number
  center_lng: number
  zoom: number
  /** Optional overlay strokes in map-local coords when using real-road mode. */
  overlay_strokes?: Stroke[]
}

export type Scene = {
  template: string
  version: number
  objects: SceneObject[]
  strokes: Stroke[]
  steps: SceneStep[]
  active_step: number
  map: SceneMap | null
}

export function newId(prefix = 'id'): string {
  return `${prefix}-${Math.random().toString(36).slice(2, 10)}${Date.now().toString(36).slice(-4)}`
}

export function createEmptyScene(templateCode = 'blank'): Scene {
  return {
    template: templateCode,
    version: 1,
    objects: [],
    strokes: [],
    steps: [
      {
        id: newId('step'),
        label: 'Step 1',
        objects: [],
        strokes: [],
        note: '',
      },
    ],
    active_step: 0,
    map: null,
  }
}

export function cloneScene(scene: Scene): Scene {
  return JSON.parse(JSON.stringify(scene)) as Scene
}

export function createObject(
  kind: SceneObjectKind,
  x: number,
  y: number,
  extras?: Partial<SceneObject>,
): SceneObject {
  return {
    id: newId('obj'),
    kind,
    x,
    y,
    rotation: 0,
    scale: 1,
    ...extras,
  }
}

export function createStroke(
  tool: Stroke['tool'],
  color: string,
  width: number,
  points: StrokePoint[] = [],
): Stroke {
  return {
    id: newId('stroke'),
    tool,
    color,
    width,
    points,
  }
}

/** Merge step overlay into base scene view for rendering. */
export function sceneAtStep(scene: Scene, stepIndex: number): {
  objects: SceneObject[]
  strokes: Stroke[]
} {
  const step = scene.steps[stepIndex]
  if (!step) {
    return { objects: scene.objects, strokes: scene.strokes }
  }
  return {
    objects: [...scene.objects, ...step.objects],
    strokes: [...scene.strokes, ...step.strokes],
  }
}
